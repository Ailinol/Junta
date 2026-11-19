import { Form, Link } from '@inertiajs/react';
import {
    ArrowRight,
    PackageCheck,
    Clock3,
    CheckCircle2,
    LoaderCircle,
} from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import type { ReactNode } from 'react';
import type { RouteDefinition } from '@/wayfinder';
import type { PurchasePage, PurchaseNextStep } from '@/types/purchase';
import { Notice } from '@/components/junta/Page';

type Field = {
    name: string;
    label: string;
    type?: string;
    value?: string | number;
    options?: { value: string; label: string }[];
    required?: boolean;
};

export function PurchaseForm({
    action,
    fields = [],
    children,
    label,
    blockedReason,
    secondary = false,
}: {
    action: RouteDefinition<'post'>;
    fields?: Field[];
    children?: ReactNode;
    label: string;
    blockedReason?: string | null;
    secondary?: boolean;
}) {
    const formId = useId();
    return (
        <Form action={action.url} method="post" className="junta-purchase-form">
            {({ errors, processing }) => (
                <>
                    {blockedReason && <Notice>{blockedReason}</Notice>}
                    {Object.keys(errors).some(
                        (key) => !fields.some((field) => field.name === key),
                    ) && (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 p-3 text-sm text-destructive"
                        >
                            {Object.entries(errors)
                                .filter(
                                    ([key]) =>
                                        !fields.some(
                                            (field) => field.name === key,
                                        ),
                                )
                                .map(([key, error]) => (
                                    <p key={key}>{error}</p>
                                ))}
                        </div>
                    )}
                    {fields.map((field) => (
                        <label
                            key={field.name}
                            className="grid gap-2 text-sm font-medium"
                        >
                            {field.label}
                            {field.options ? (
                                <select
                                    name={field.name}
                                    defaultValue={field.value}
                                    className="junta-input"
                                    required={field.required ?? true}
                                    aria-invalid={!!errors[field.name]}
                                    aria-describedby={
                                        errors[field.name]
                                            ? formId + field.name
                                            : undefined
                                    }
                                >
                                    {field.options.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <input
                                    name={field.name}
                                    type={field.type ?? 'text'}
                                    defaultValue={field.value}
                                    step={
                                        field.type === 'number'
                                            ? '0.01'
                                            : undefined
                                    }
                                    className="junta-input"
                                    required={field.required ?? true}
                                    aria-invalid={!!errors[field.name]}
                                    aria-describedby={
                                        errors[field.name]
                                            ? formId + field.name
                                            : undefined
                                    }
                                />
                            )}
                            {errors[field.name] && (
                                <span
                                    id={formId + field.name}
                                    role="alert"
                                    className="text-sm font-normal text-destructive"
                                >
                                    {errors[field.name]}
                                </span>
                            )}
                        </label>
                    ))}
                    {children}
                    <button
                        type="submit"
                        className={
                            secondary
                                ? 'junta-btn junta-btn-md justify-self-start junta-btn-outline'
                                : 'junta-btn junta-btn-md justify-self-start junta-btn-primary'
                        }
                        disabled={processing || !!blockedReason}
                    >
                        {processing && (
                            <LoaderCircle
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {processing ? 'A processar…' : label}
                    </button>
                </>
            )}
        </Form>
    );
}

export function NextStepNotice({
    step,
    children,
}: {
    step: PurchaseNextStep;
    children?: ReactNode;
}) {
    const success = ['ready', 'received', 'refunded', 'completed'].includes(
        step.code,
    );
    const Icon =
        step.code === 'ready' ? PackageCheck : success ? CheckCircle2 : Clock3;
    return (
        <section
            className="junta-next-step"
            aria-label="Estado e próximo passo"
            data-tone={
                success
                    ? 'success'
                    : [
                            'payment_pending',
                            'refund_pending',
                            'payments',
                        ].includes(step.code)
                      ? 'warning'
                      : 'default'
            }
        >
            <Icon
                className="mt-1 size-5 shrink-0 text-primary"
                aria-hidden="true"
            />
            <div className="min-w-0 flex-1">
                <p className="junta-eyebrow">
                    {step.action ? 'A sua próxima etapa' : 'Estado da compra'}
                </p>
                <h2>{step.title}</h2>
                <p data-description>{step.description}</p>
                {children && (
                    <div data-action className="flex items-start gap-2">
                        <ArrowRight
                            className="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <div>{children}</div>
                    </div>
                )}
            </div>
        </section>
    );
}

export function PaymentDeadline({
    value,
    serverNow,
}: {
    value: string;
    serverNow: string;
}) {
    const [now, setNow] = useState(() => new Date(serverNow).getTime());
    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);
    const remaining = Math.max(0, new Date(value).getTime() - now);
    const minutes = Math.ceil(remaining / 60000);
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const today = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Africa/Maputo',
    });
    const sameDay =
        today.format(new Date(now)) === today.format(new Date(value));
    const time = new Date(value).toLocaleTimeString('pt-MZ', {
        timeZone: 'Africa/Maputo',
        hour: '2-digit',
        minute: '2-digit',
    });
    return (
        <p className="my-4 text-sm text-muted-foreground">
            <time dateTime={value}>
                {remaining === 0
                    ? 'Prazo terminado'
                    : sameDay
                      ? `Termina hoje às ${time}`
                      : `Termina em ${commercialTime(value)}`}
            </time>
            {remaining > 0 && (
                <span className="block tabular-nums">
                    Faltam {days > 0 ? `${days} d ` : ''}
                    {hours} h {minutes % 60} min · hora de Maputo
                </span>
            )}
        </p>
    );
}

export function commercialTime(value: string | null | undefined): string {
    return value
        ? new Date(value).toLocaleString('pt-MZ', {
              timeZone: 'Africa/Maputo',
          }) + ' (Maputo)'
        : 'Por definir';
}

export function PurchasePagination({ page }: { page: PurchasePage<unknown> }) {
    return (
        <nav
            aria-label="Paginação"
            className="my-4 flex flex-wrap items-center gap-4 text-sm"
        >
            <span>
                {page.total} registos · página {page.current_page} de{' '}
                {page.last_page}
            </span>
            {page.prev_page_url && (
                <Link
                    href={page.prev_page_url}
                    className="text-primary underline"
                >
                    Anterior
                </Link>
            )}
            {page.next_page_url && (
                <Link
                    href={page.next_page_url}
                    className="text-primary underline"
                >
                    Seguinte
                </Link>
            )}
        </nav>
    );
}

export function purchaseState(value: string): string {
    const labels: Record<string, string> = {
        draft: 'Preparação privada',
        evaluation: 'Avaliação de propostas',
        selection: 'Proposta escolhida',
        confirmed: 'Compra confirmada',
        interest: 'Interesse',
        payment: 'Pagamento aberto',
        review: 'Conferência pendente',
        supply: 'Fornecimento',
        completed: 'Concluída',
        cancelled: 'Cancelado',
        awaiting_payment: 'Por pagar',
        payment_pending: 'A aguardar confirmação',
        paid: 'Pago',
        refund_pending: 'Reembolso solicitado',
        refunded: 'Reembolsado',
        waiting: 'A aguardar mercadoria',
        ready: 'Separado para recolha ou entrega',
        received: 'Recebido',
        pending: 'Pendente',
        failed: 'Falhou',
        requested: 'Solicitado',
        open: 'Em análise',
        resolved: 'Resolvido',
        refund_approved: 'Reembolso aprovado',
        missing: 'Mercadoria em falta',
        damaged: 'Mercadoria danificada',
        not_received: 'Não recebido',
    };
    return labels[value] ?? value;
}

export function money(cents: number): string {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'MZN',
        currencyDisplay: 'code',
    }).format(cents / 100);
}
