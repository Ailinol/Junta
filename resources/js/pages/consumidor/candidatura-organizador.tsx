import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock3, FileCheck2, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import { Page, DetailList, Notice } from '@/components/junta/Page';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { store } from '@/routes/consumidor/candidatura';
import { dashboard } from '@/routes/organizador';
import { formatDate } from '@/lib/formatters';

export default function OrganizerApplication() {
    const { auth } = usePage().props;
    const { data } = usePrototypeStore();
    const application = data.organizerApplication;
    const form = useForm({
        fullName: application?.fullName ?? auth.user.name,
        phone: application?.phone ?? data.consumerProfile.phone,
        location: application?.location ?? data.consumerProfile.city,
        motivation: application?.motivation ?? '',
    });
    const approved = !!auth.permissions?.organizer;
    const canApply =
        !approved && (!application || application.status === 'rejected');
    return (
        <Page
            title="Ser organizador"
            description="Solicite acesso para criar e gerir campanhas. Mantém a mesma conta e o acesso de consumidor."
            narrow
        >
            <div className="grid gap-5">
                {(application || approved) && (
                    <OperationalPanel
                        title={
                            approved
                                ? 'Acesso de organizador activo'
                                : application?.status === 'pending'
                                  ? 'Candidatura em avaliação'
                                  : 'Candidatura rejeitada'
                        }
                    >
                        <div className="flex items-start gap-4">
                            {approved ? (
                                <CheckCircle2 className="size-8 shrink-0 text-primary" />
                            ) : (
                                <Clock3 className="size-8 shrink-0 text-primary" />
                            )}
                            <div className="min-w-0">
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {approved
                                        ? 'Pode participar como consumidor e organizar campanhas na mesma conta.'
                                        : application?.status === 'pending'
                                          ? 'Aguarde a decisão administrativa. Enviar o pedido não concede privilégios de organizador.'
                                          : application?.decisionReason}
                                </p>
                                {application && (
                                    <DetailList
                                        items={[
                                            {
                                                label: 'Enviada em',
                                                value: formatDate(
                                                    application.createdAt,
                                                ),
                                            },
                                            {
                                                label: 'Estado',
                                                value: approved
                                                    ? 'Aprovada'
                                                    : application.status ===
                                                        'pending'
                                                      ? 'Pendente'
                                                      : 'Rejeitada',
                                            },
                                            ...(application.reviewedAt
                                                ? [
                                                      {
                                                          label: 'Decidida em',
                                                          value: formatDate(
                                                              application.reviewedAt,
                                                          ),
                                                      },
                                                  ]
                                                : []),
                                        ]}
                                    />
                                )}
                                <div className="mt-4 flex flex-wrap gap-3">
                                    {approved ? (
                                        <Link
                                            href={dashboard()}
                                            className="junta-btn junta-btn-md junta-btn-primary"
                                        >
                                            Abrir área de organizador
                                        </Link>
                                    ) : (
                                        <button
                                            type="button"
                                            className="junta-btn junta-btn-md junta-btn-outline"
                                            onClick={() =>
                                                router.reload({
                                                    only: ['business', 'auth'],
                                                })
                                            }
                                        >
                                            <RefreshCw className="size-4" />
                                            Actualizar estado
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </OperationalPanel>
                )}
                {canApply && (
                    <OperationalPanel
                        title={
                            application
                                ? 'Rever e reenviar candidatura'
                                : 'Dados para avaliação'
                        }
                    >
                        <form
                            className="space-y-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(store.url(), {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        toast.success(
                                            'Candidatura enviada para avaliação.',
                                        ),
                                });
                            }}
                        >
                            <div className="grid gap-5 sm:grid-cols-2">
                                {[
                                    { key: 'fullName', label: 'Nome completo' },
                                    { key: 'phone', label: 'Telefone' },
                                    { key: 'location', label: 'Localidade' },
                                ].map((field) => (
                                    <div
                                        key={field.key}
                                        className={
                                            field.key === 'fullName'
                                                ? 'sm:col-span-2'
                                                : ''
                                        }
                                    >
                                        <label
                                            className="mb-2 block text-sm font-medium"
                                            htmlFor={field.key}
                                        >
                                            {field.label}
                                        </label>
                                        <input
                                            id={field.key}
                                            className="junta-input"
                                            required
                                            value={
                                                form.data[
                                                    field.key as
                                                        | 'fullName'
                                                        | 'phone'
                                                        | 'location'
                                                ]
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    field.key as
                                                        | 'fullName'
                                                        | 'phone'
                                                        | 'location',
                                                    e.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                !!form.errors[
                                                    field.key as 'fullName'
                                                ]
                                            }
                                        />
                                        {form.errors[
                                            field.key as 'fullName'
                                        ] && (
                                            <p className="mt-2 text-sm text-destructive">
                                                {
                                                    form.errors[
                                                        field.key as 'fullName'
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <div>
                                <label
                                    htmlFor="motivation"
                                    className="mb-2 block text-sm font-medium"
                                >
                                    Porque pretende organizar compras
                                    colectivas?
                                </label>
                                <textarea
                                    id="motivation"
                                    className="junta-input min-h-32"
                                    required
                                    maxLength={3000}
                                    value={form.data.motivation}
                                    onChange={(e) =>
                                        form.setData(
                                            'motivation',
                                            e.target.value,
                                        )
                                    }
                                />
                                {form.errors.motivation && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {form.errors.motivation}
                                    </p>
                                )}
                            </div>
                            {Object.entries(form.errors)
                                .filter(
                                    ([key]) =>
                                        ![
                                            'fullName',
                                            'phone',
                                            'location',
                                            'motivation',
                                        ].includes(key),
                                )
                                .map(([key, message]) => (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                        key={key}
                                    >
                                        {message}
                                    </p>
                                ))}
                            <Notice>
                                Apenas um administrador pode aprovar o pedido. A
                                decisão fica registada e será apresentada nesta
                                conta.
                            </Notice>
                            <button
                                disabled={form.processing}
                                className="junta-btn junta-btn-md junta-btn-primary"
                            >
                                <FileCheck2 className="size-4" />
                                {form.processing
                                    ? 'A enviar…'
                                    : 'Enviar candidatura'}
                            </button>
                        </form>
                    </OperationalPanel>
                )}
            </div>
        </Page>
    );
}
