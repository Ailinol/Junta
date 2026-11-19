import { useForm } from '@inertiajs/react';
import { Fragment, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { ConfirmDialog } from '@/components/junta/OrganizerUi';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { campaignCategories } from '@/lib/campaign-presentation';
import { mutate, errorMessage } from '@/lib/server-mutation';
import type { Campaign } from '@/types/junta';

type Value = string | number | boolean | string[] | null;
export type EditorField = {
    key: string;
    label: string;
    type?:
        | 'text'
        | 'email'
        | 'number'
        | 'date'
        | 'datetime-local'
        | 'url'
        | 'textarea'
        | 'select'
        | 'boolean'
        | 'categories';
    required?: boolean;
    options?: { value: string; label: string }[];
    hint?: string;
    section?: string;
    maxLength?: number;
};
export type MutationRoute = {
    url: string;
    method: 'post' | 'patch' | 'delete';
};
export function ResourceDrawer({
    title,
    description,
    children,
    onClose,
    busy = false,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    onClose: () => void;
    busy?: boolean;
}) {
    return (
        <Sheet
            open
            onOpenChange={(open) => {
                if (!open && !busy) onClose();
            }}
        >
            <SheetContent
                side="right"
                className="admin-resource-drawer w-full overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>
                        {description ??
                            'Consulte os dados e as operações disponíveis para este registo.'}
                    </SheetDescription>
                </SheetHeader>
                <div className="admin-resource-content">{children}</div>
            </SheetContent>
        </Sheet>
    );
}
export function ResourceEditor({
    title,
    description,
    fields,
    initial,
    route,
    onClose,
}: {
    title: string;
    description?: string;
    fields: EditorField[];
    initial: Record<string, Value>;
    route: MutationRoute;
    onClose: () => void;
}) {
    const form = useForm(initial);
    const preview: Campaign = {
        id: 0,
        title: String(form.data.title ?? ''),
        description: String(form.data.description ?? ''),
        category: (form.data.category ?? 'outro') as Campaign['category'],
        status: 'rascunho',
        organizerName: '',
        targetQuantity: Number(form.data.targetQuantity ?? 0),
        currentQuantity: 0,
        unit: String(form.data.unit ?? ''),
        participantCount: 0,
        deadline: String(form.data.deadline ?? ''),
        location: String(form.data.location ?? ''),
        deliveryMode: 'recolha',
        imageUrl: String(form.data.imageUrl ?? ''),
        createdAt: '',
        updatedAt: '',
    };
    return (
        <ResourceDrawer
            title={title}
            description={description}
            onClose={onClose}
            busy={form.processing}
        >
            <form
                className="admin-resource-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.submit(route.method, route.url, {
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success('Registo guardado.');
                            onClose();
                        },
                    });
                }}
            >
                {Object.keys(form.errors).some(
                    (key) => !fields.some((field) => field.key === key),
                ) && (
                    <div role="alert" className="admin-form-errors">
                        {Object.entries(form.errors)
                            .filter(
                                ([key]) =>
                                    !fields.some((field) => field.key === key),
                            )
                            .map(([key, message]) => (
                                <p key={key}>{message}</p>
                            ))}
                    </div>
                )}
                {fields.map((field, index) => {
                    const value = form.data[field.key];
                    const error = form.errors[field.key];
                    const id = 'resource-' + field.key;
                    return (
                        <Fragment key={field.key}>
                            {field.section &&
                                field.section !==
                                    fields[index - 1]?.section && (
                                    <h3>{field.section}</h3>
                                )}
                            <div
                                className="admin-resource-field"
                                data-field={field.key}
                            >
                                {field.type === 'boolean' ? (
                                    <label>
                                        <input
                                            type="checkbox"
                                            checked={Boolean(value)}
                                            disabled={form.processing}
                                            onChange={(event) =>
                                                form.setData(
                                                    field.key,
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        {field.label}
                                    </label>
                                ) : (
                                    <>
                                        <label htmlFor={id}>
                                            {field.label}
                                        </label>
                                        {field.type === 'categories' ? (
                                            <div className="admin-category-options">
                                                {Object.entries(
                                                    campaignCategories,
                                                ).map(([key, label]) => (
                                                    <label key={key}>
                                                        <input
                                                            type="checkbox"
                                                            checked={
                                                                Array.isArray(
                                                                    value,
                                                                ) &&
                                                                value.includes(
                                                                    key,
                                                                )
                                                            }
                                                            disabled={
                                                                form.processing
                                                            }
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    field.key,
                                                                    event.target
                                                                        .checked
                                                                        ? [
                                                                              ...(Array.isArray(
                                                                                  value,
                                                                              )
                                                                                  ? value
                                                                                  : []),
                                                                              key,
                                                                          ]
                                                                        : (Array.isArray(
                                                                              value,
                                                                          )
                                                                              ? value
                                                                              : []
                                                                          ).filter(
                                                                              (
                                                                                  item,
                                                                              ) =>
                                                                                  item !==
                                                                                  key,
                                                                          ),
                                                                )
                                                            }
                                                        />
                                                        {label}
                                                    </label>
                                                ))}
                                            </div>
                                        ) : field.type === 'textarea' ? (
                                            <textarea
                                                id={id}
                                                name={field.key}
                                                className="junta-input min-h-28"
                                                value={String(value ?? '')}
                                                required={field.required}
                                                maxLength={field.maxLength}
                                                disabled={form.processing}
                                                aria-invalid={!!error}
                                                aria-describedby={
                                                    error
                                                        ? id + '-error'
                                                        : undefined
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        field.key,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        ) : field.type === 'select' ? (
                                            <select
                                                id={id}
                                                name={field.key}
                                                className="junta-input"
                                                value={String(value ?? '')}
                                                required={field.required}
                                                disabled={form.processing}
                                                aria-invalid={!!error}
                                                onChange={(event) =>
                                                    form.setData(
                                                        field.key,
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Seleccionar
                                                </option>
                                                {field.options?.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        ) : (
                                            <input
                                                id={id}
                                                name={field.key}
                                                type={field.type ?? 'text'}
                                                className="junta-input"
                                                value={String(value ?? '')}
                                                required={field.required}
                                                disabled={form.processing}
                                                maxLength={field.maxLength}
                                                step={
                                                    field.type === 'number'
                                                        ? 'any'
                                                        : undefined
                                                }
                                                aria-invalid={!!error}
                                                aria-describedby={
                                                    error
                                                        ? id + '-error'
                                                        : undefined
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        field.key,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </>
                                )}
                                {field.hint && <small>{field.hint}</small>}
                                {error && (
                                    <p
                                        id={id + '-error'}
                                        role="alert"
                                        className="text-destructive"
                                    >
                                        {error}
                                    </p>
                                )}
                                {field.key === 'imageUrl' && (
                                    <CampaignVisual
                                        key={preview.imageUrl}
                                        campaign={preview}
                                        categoryFallback
                                        className="mt-3"
                                    />
                                )}
                            </div>
                        </Fragment>
                    );
                })}
                <div className="admin-resource-actions">
                    <button
                        type="button"
                        className="junta-btn junta-btn-md junta-btn-outline"
                        disabled={form.processing}
                        onClick={onClose}
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        className="junta-btn junta-btn-md junta-btn-primary"
                        disabled={form.processing}
                    >
                        {form.processing ? 'A guardar…' : 'Guardar'}
                    </button>
                </div>
            </form>
        </ResourceDrawer>
    );
}
export function ResourceRemoval({
    label,
    route,
    onClose,
}: {
    label: string;
    route: MutationRoute;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    return (
        <ConfirmDialog
            open
            onOpenChange={(open) => {
                if (!open && !processing) onClose();
            }}
            className="admin-dialog"
            title="Remover registo?"
            description={`${label}. A remoção é permanente e só é permitida sem registos associados.`}
            danger
            processing={processing}
            confirmLabel="Remover"
            onConfirm={() => {
                setProcessing(true);
                void mutate(route)
                    .then(() => {
                        toast.success('Registo removido.');
                        onClose();
                    })
                    .catch((failure) => setError(errorMessage(failure)))
                    .finally(() => setProcessing(false));
            }}
        >
            {error && (
                <p role="alert" className="text-destructive">
                    {error}
                </p>
            )}
        </ConfirmDialog>
    );
}
