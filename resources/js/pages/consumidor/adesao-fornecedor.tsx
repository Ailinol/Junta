import { usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { Page } from '@/components/junta/Page';
import { campaignCategories } from '@/lib/campaign-presentation';
import { mutate, errorMessage, ValidationFailure } from '@/lib/server-mutation';
import { store } from '@/routes/fornecedor/adesao';

export default function SupplierRegistration() {
    const { auth } = usePage().props;
    const [form, setForm] = useState({
        companyName: '',
        nuit: '',
        contactName: auth.user.name,
        email: auth.user.email,
        phone: '',
        location: '',
        description: '',
        categories: [] as string[],
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const fields = [
        ['companyName', 'Nome da empresa', 'text'],
        ['nuit', 'NUIT', 'text'],
        ['contactName', 'Pessoa de contacto', 'text'],
        ['email', 'Email comercial', 'email'],
        ['phone', 'Telefone', 'tel'],
        ['location', 'Localização', 'text'],
    ] as const;
    const submit = async (event: FormEvent) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        setError('');
        setErrors({});
        try {
            await mutate(store(), form);
        } catch (failure) {
            if (failure instanceof ValidationFailure) setErrors(failure.errors);
            else setError(errorMessage(failure));
        } finally {
            setSaving(false);
        }
    };
    return (
        <Page
            title="Aderir como fornecedor"
            description="Crie um perfil comercial ligado à sua conta. A apresentação de propostas depende de verificação administrativa."
            narrow
        >
            <OperationalPanel title="Identificação comercial">
                <form
                    onSubmit={(event) => void submit(event)}
                    className="space-y-6"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        {fields.map(([key, label, type]) => (
                            <div key={key}>
                                <label
                                    htmlFor={'supplier-' + key}
                                    className="mb-2 block text-sm font-medium"
                                >
                                    {label}
                                </label>
                                <input
                                    id={'supplier-' + key}
                                    type={type}
                                    required
                                    maxLength={
                                        key === 'nuit' || key === 'phone'
                                            ? 40
                                            : 255
                                    }
                                    value={form[key]}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            [key]: event.target.value,
                                        })
                                    }
                                    className="junta-input"
                                />
                                {errors[key] && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors[key]}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                    <fieldset>
                        <legend className="mb-3 text-sm font-medium">
                            Categorias de fornecimento
                        </legend>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {Object.entries(campaignCategories).map(
                                ([value, label]) => (
                                    <label
                                        key={value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.categories.includes(
                                                value,
                                            )}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    categories: event.target
                                                        .checked
                                                        ? [
                                                              ...form.categories,
                                                              value,
                                                          ]
                                                        : form.categories.filter(
                                                              (category) =>
                                                                  category !==
                                                                  value,
                                                          ),
                                                })
                                            }
                                        />
                                        {label}
                                    </label>
                                ),
                            )}
                        </div>
                        {errors.categories && (
                            <p className="mt-2 text-xs text-destructive">
                                {errors.categories}
                            </p>
                        )}
                    </fieldset>
                    <div>
                        <label
                            htmlFor="supplier-description"
                            className="mb-2 block text-sm font-medium"
                        >
                            Apresentação e capacidade de fornecimento
                        </label>
                        <textarea
                            id="supplier-description"
                            value={form.description}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    description: event.target.value,
                                })
                            }
                            maxLength={5000}
                            className="junta-input min-h-28"
                        />
                    </div>
                    <p className="rounded-xl border border-border bg-muted p-4 text-sm leading-6 text-muted-foreground">
                        A administração analisará os dados. Até à verificação
                        pode consultar o perfil e as oportunidades; não pode
                        submeter propostas.
                    </p>
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    <button
                        disabled={saving || form.categories.length === 0}
                        className="junta-btn junta-btn-md junta-btn-primary"
                    >
                        {saving ? 'A enviar…' : 'Enviar para verificação'}
                    </button>
                </form>
            </OperationalPanel>
        </Page>
    );
}
