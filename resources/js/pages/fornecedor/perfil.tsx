import type { PrototypeStore } from '@/hooks/use-prototype-store';
import { PageHeader } from '@/components/junta/PageHeader';
// resources/js/Pages/Fornecedor/Perfil.tsx

import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { Head } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    Check,
    CheckCircle2,
    Mail,
    MapPin,
    Phone,
    Save,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { mutate, errorMessage } from '@/lib/server-mutation';
import { update as saveProfile } from '@/routes/fornecedor/perfil';

const categoryOptions = [
    { label: 'Construção civil', value: 'construcao' },
    { label: 'Alimentação', value: 'alimentacao' },
    { label: 'Electrónica', value: 'eletronicos' },
    { label: 'Escritório', value: 'escritorio' },
    { label: 'Agrícola', value: 'agricola' },
    { label: 'Saúde', value: 'saude' },
    { label: 'Serviços', value: 'servicos' },
    { label: 'Outro', value: 'outro' },
] as const;

export default function PerfilComercialFornecedor() {
    const { data, isLoaded } = usePrototypeStore();
    if (!isLoaded)
        return (
            <div
                role="status"
                aria-label="A carregar perfil"
                className="m-8 h-64 animate-pulse rounded-xl bg-muted"
            />
        );
    return <ProfileForm initialProfile={data.supplierProfile} />;
}
function ProfileForm({
    initialProfile,
}: {
    initialProfile: PrototypeStore['supplierProfile'];
}) {
    const { data } = usePrototypeStore();
    const [companyName, setCompanyName] = useState(initialProfile.companyName);
    const [nuit] = useState(initialProfile.nuit);
    const [contactName, setContactName] = useState(initialProfile.contactName);
    const [email, setEmail] = useState(initialProfile.email);
    const [phone, setPhone] = useState(initialProfile.phone);
    const [location, setLocation] = useState(initialProfile.location);
    const [description, setDescription] = useState(
        initialProfile.description || '',
    );
    const [saved, setSaved] = useState(false);
    const [selectedCategories, setSelectedCategories] = useState(
        initialProfile.categories,
    );
    const [saving, setSaving] = useState(false);

    const handleSave = async () => {
        if (saving) return;
        if (selectedCategories.length === 0) {
            toast.error('Seleccione pelo menos uma categoria de fornecimento.');
            return;
        }
        setSaving(true);
        try {
            await mutate(saveProfile(), {
                companyName,
                contactName,
                email,
                phone,
                location,
                description,
                categories: selectedCategories,
            });
            setSaved(true);
            setTimeout(() => setSaved(false), 2500);
        } catch (failure) {
            toast.error(errorMessage(failure));
        } finally {
            setSaving(false);
        }
    };

    const categories = categoryOptions.map((category) => ({
        ...category,
        active: selectedCategories.includes(category.value),
    }));
    const activeCategories = categories.filter((c) => c.active).length;
    const verified = data.supplierProfile.verified;

    return (
        <>
            <Head title="Perfil comercial — Fornecedor Junta" />

            <div className="junta-page">
                <div className="junta-shell-wide py-8 md:py-10">
                    {/* ---------------- Cabeçalho ---------------- */}
                    <PageHeader
                        title="Perfil comercial"
                        actions={
                            <>
                                {verified && (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-junta-success-soft)] px-3 py-1 text-xs font-bold tracking-wide text-[var(--color-junta-success)] uppercase">
                                        <ShieldCheck className="size-3.5" />
                                        Fornecedor verificado
                                    </span>
                                )}
                            </>
                        }
                    />

                    {/* ---------------- Conteúdo ---------------- */}
                    <div className="mt-6 grid gap-6 lg:grid-cols-12">
                        {/* Identificação da empresa */}
                        <section className="lg:col-span-8">
                            <Panel
                                title="Identificação da empresa"
                                description="Visível para organizadores em tabelas comparativas."
                                icon={<Building2 className="size-5" />}
                            >
                                <div className="space-y-5">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field
                                            id="company"
                                            label="Razão social"
                                            required
                                        >
                                            <input
                                                id="company"
                                                type="text"
                                                value={companyName}
                                                onChange={(e) =>
                                                    setCompanyName(
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 w-full rounded-[var(--radius-junta-md)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] px-4 text-base text-[var(--color-junta-foreground)] outline-none focus:border-[var(--color-junta-primary)] focus:ring-2 focus:ring-[var(--color-junta-primary)]/15"
                                            />
                                        </Field>

                                        <Field
                                            id="nuit"
                                            label="NUIT"
                                            hint="Nº único de identificação"
                                            required
                                        >
                                            <input
                                                id="nuit"
                                                type="text"
                                                value={nuit}
                                                readOnly
                                                className="h-11 w-full rounded-[var(--radius-junta-md)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] px-4 text-base text-[var(--color-junta-foreground)] outline-none focus:border-[var(--color-junta-primary)] focus:ring-2 focus:ring-[var(--color-junta-primary)]/15"
                                            />
                                        </Field>
                                    </div>

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field
                                            id="contact"
                                            label="Pessoa de contacto"
                                            required
                                        >
                                            <input
                                                id="contact"
                                                type="text"
                                                value={contactName}
                                                onChange={(e) =>
                                                    setContactName(
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 w-full rounded-[var(--radius-junta-md)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] px-4 text-base text-[var(--color-junta-foreground)] outline-none focus:border-[var(--color-junta-primary)] focus:ring-2 focus:ring-[var(--color-junta-primary)]/15"
                                            />
                                        </Field>

                                        <Field
                                            id="loc"
                                            label="Armazém principal"
                                            icon={<MapPin className="size-4" />}
                                            required
                                        >
                                            <input
                                                id="loc"
                                                type="text"
                                                value={location}
                                                onChange={(e) =>
                                                    setLocation(e.target.value)
                                                }
                                                className="h-11 w-full rounded-[var(--radius-junta-md)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] px-4 text-base text-[var(--color-junta-foreground)] outline-none focus:border-[var(--color-junta-primary)] focus:ring-2 focus:ring-[var(--color-junta-primary)]/15"
                                            />
                                        </Field>
                                    </div>

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field
                                            id="email"
                                            label="Email comercial"
                                            icon={<Mail className="size-4" />}
                                        >
                                            <input
                                                id="email"
                                                type="email"
                                                value={email}
                                                onChange={(e) =>
                                                    setEmail(e.target.value)
                                                }
                                                className="h-11 w-full rounded-[var(--radius-junta-md)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] px-4 text-base text-[var(--color-junta-foreground)] outline-none focus:border-[var(--color-junta-primary)] focus:ring-2 focus:ring-[var(--color-junta-primary)]/15"
                                            />
                                        </Field>

                                        <Field
                                            id="phone"
                                            label="Telefone"
                                            icon={<Phone className="size-4" />}
                                        >
                                            <input
                                                id="phone"
                                                type="tel"
                                                value={phone}
                                                onChange={(e) =>
                                                    setPhone(e.target.value)
                                                }
                                                className="h-11 w-full rounded-[var(--radius-junta-md)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] px-4 text-base text-[var(--color-junta-foreground)] outline-none focus:border-[var(--color-junta-primary)] focus:ring-2 focus:ring-[var(--color-junta-primary)]/15"
                                            />
                                        </Field>
                                    </div>

                                    <Field
                                        id="desc"
                                        label="Apresentação e capacidade"
                                        hint="Opcional"
                                    >
                                        <textarea
                                            id="desc"
                                            rows={4}
                                            value={description}
                                            onChange={(e) =>
                                                setDescription(e.target.value)
                                            }
                                            placeholder="Descreva a empresa e a capacidade de fornecimento."
                                            className="w-full rounded-[var(--radius-junta-md)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] p-3 text-base text-[var(--color-junta-foreground)] outline-none focus:border-[var(--color-junta-primary)] focus:ring-2 focus:ring-[var(--color-junta-primary)]/15"
                                        />
                                    </Field>
                                </div>
                            </Panel>
                        </section>

                        {/* Sidebar: verificação + categorias */}
                        <aside className="space-y-4 lg:col-span-4">
                            {/* Estado de verificação */}
                            <Panel
                                title="Estado da conta"
                                icon={<ShieldCheck className="size-5" />}
                            >
                                {verified ? (
                                    <div className="flex items-start gap-3 rounded-[var(--radius-junta-md)] bg-[var(--color-junta-success-soft)]/60 p-3">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-[var(--color-junta-success)]" />
                                        <div>
                                            <p className="text-sm font-semibold text-[var(--color-junta-success)]">
                                                Verificado
                                            </p>
                                            <p className="mt-0.5 text-xs text-[var(--color-junta-muted-foreground)]">
                                                Verificação registada pela
                                                administração.
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-start gap-3 rounded-[var(--radius-junta-md)] bg-[var(--color-junta-warning-soft)]/60 p-3">
                                        <ShieldCheck className="mt-0.5 size-4 shrink-0 text-[var(--color-junta-warning)]" />
                                        <div>
                                            <p className="text-sm font-semibold text-[var(--color-junta-warning)]">
                                                Por verificar
                                            </p>
                                            <p className="mt-0.5 text-xs text-[var(--color-junta-muted-foreground)]">
                                                Aguarde a verificação
                                                administrativa para submeter
                                                propostas.
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </Panel>

                            {/* Categorias */}
                            <Panel
                                title="Categorias atendidas"
                                subtitle={`${activeCategories} activa${activeCategories !== 1 ? 's' : ''} de ${categories.length}`}
                                icon={<Briefcase className="size-5" />}
                            >
                                <div className="flex flex-wrap gap-2">
                                    {categories.map((c) => (
                                        <button
                                            type="button"
                                            aria-pressed={c.active}
                                            onClick={() =>
                                                setSelectedCategories(
                                                    c.active
                                                        ? selectedCategories.filter(
                                                              (value) =>
                                                                  value !==
                                                                  c.value,
                                                          )
                                                        : [
                                                              ...selectedCategories,
                                                              c.value,
                                                          ],
                                                )
                                            }
                                            key={c.label}
                                            className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${
                                                c.active
                                                    ? 'bg-[var(--color-junta-primary)] text-primary-foreground'
                                                    : 'bg-[var(--color-junta-surface-muted)] text-[var(--color-junta-muted-foreground)]'
                                            }`}
                                        >
                                            {c.active && (
                                                <Check className="mr-1 size-3" />
                                            )}
                                            {c.label}
                                        </button>
                                    ))}
                                </div>
                                <p className="mt-3 text-xs text-[var(--color-junta-muted-foreground)]">
                                    Categorias registadas no perfil comercial.
                                </p>
                            </Panel>
                        </aside>
                    </div>

                    {/* ---------------- Barra sticky de guardar ---------------- */}
                    <div className="mt-6">
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-junta-xl)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)]/95 px-5 py-3 shadow-[var(--shadow-junta-raised)] backdrop-blur">
                            <div className="flex items-center gap-2 text-sm">
                                {saved ? (
                                    <>
                                        <span className="flex size-6 items-center justify-center rounded-full bg-[var(--color-junta-success-soft)] text-[var(--color-junta-success)]">
                                            <Check className="size-4" />
                                        </span>
                                        <span className="font-medium text-[var(--color-junta-success)]">
                                            Alterações guardadas
                                        </span>
                                    </>
                                ) : (
                                    <span className="text-[var(--color-junta-muted-foreground)]">
                                        As alterações não são guardadas
                                        automaticamente.
                                    </span>
                                )}
                            </div>
                            <button
                                type="button"
                                disabled={saving}
                                onClick={() => {
                                    void handleSave();
                                }}
                                className="junta-btn junta-btn-md junta-btn-primary"
                            >
                                <Save className="size-4" />
                                Guardar alterações
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

/* ------------------------------------------------------------------ */
/* Panel wrapper                                                     */
/* ------------------------------------------------------------------ */

function Panel({
    title,
    description,
    subtitle,
    icon,
    children,
}: {
    title: string;
    description?: string;
    subtitle?: string;
    icon?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-[var(--radius-junta-xl)] border border-[var(--color-junta-border)] bg-[var(--color-junta-surface)] shadow-[var(--shadow-junta-card)]">
            <header className="flex items-center gap-3 border-b border-primary/15 bg-junta-primary-soft px-5 py-4">
                {icon && (
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--color-junta-primary-soft)] text-[var(--color-junta-primary)]">
                        {icon}
                    </span>
                )}
                <div className="min-w-0">
                    <h2 className="junta-serif text-base font-semibold text-[var(--color-junta-foreground)]">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-0.5 text-xs text-[var(--color-junta-muted-foreground)]">
                            {description}
                        </p>
                    )}
                    {subtitle && (
                        <p className="mt-0.5 text-xs text-[var(--color-junta-muted-foreground)]">
                            {subtitle}
                        </p>
                    )}
                </div>
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

/* ------------------------------------------------------------------ */
/* Field                                                             */
/* ------------------------------------------------------------------ */

function Field({
    id,
    label,
    hint,
    icon,
    required,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    icon?: React.ReactNode;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label
                htmlFor={id}
                className="mb-2 flex items-center justify-between text-sm font-semibold text-[var(--color-junta-foreground)]"
            >
                <span className="flex items-center gap-2">
                    {icon && (
                        <span className="text-[var(--color-junta-muted-foreground)]">
                            {icon}
                        </span>
                    )}
                    {label}
                    {required && (
                        <span className="text-[var(--color-junta-error)]">
                            *
                        </span>
                    )}
                </span>
                {hint && (
                    <span className="text-xs font-normal text-[var(--color-junta-muted-foreground)]">
                        {hint}
                    </span>
                )}
            </label>
            {children}
        </div>
    );
}
