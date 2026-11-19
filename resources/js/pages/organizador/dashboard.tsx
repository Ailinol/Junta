import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Plus } from 'lucide-react';
import {
    PageShell,
    DeadlineLabel,
    KpiStrip,
} from '@/components/junta/OrganizerUi';
import { EmptyState } from '@/components/junta/EmptyState';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { OrganizerCampaignTable } from '@/components/junta/OrganizerCampaignTable';
import { organizerActionUrl } from '@/lib/organizer-routes';
import { criar, index } from '@/routes/organizador/campanhas';
import type { OrganizerDashboard } from '@/types/organizer';

export default function Dashboard({
    organizerDashboard: data,
}: {
    organizerDashboard: OrganizerDashboard;
}) {
    const { auth } = usePage().props;
    const name = auth.user.name.split(' ')[0];
    return (
        <PageShell
            title={`Olá, ${name}`}
            description="As decisões que fazem avançar as suas campanhas."
            actions={
                <Link
                    href={criar()}
                    className="junta-btn junta-btn-md junta-btn-primary"
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Criar campanha
                </Link>
            }
        >
            <OperationalPanel
                title="Precisa de si"
                emphasis="brand"
                description="As tarefas mais urgentes aparecem primeiro."
            >
                {data.tasks.length ? (
                    <ul className="organizer-task-list">
                        {data.tasks.map((view) => (
                            <li key={view.campaign.id}>
                                <div className="min-w-0 flex-1">
                                    <h3>
                                        {view.state.primaryAction?.label}
                                        {view.state.code === 'evaluation' &&
                                            ` · ${view.metrics.openProposals} propostas`}
                                    </h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {view.campaign.title}
                                    </p>
                                </div>
                                <DeadlineLabel deadline={view.deadline} />
                                <Link
                                    href={organizerActionUrl(
                                        view.campaign.id,
                                        view.state.primaryAction,
                                    )}
                                    className="junta-btn junta-btn-sm junta-btn-outline"
                                >
                                    Abrir tarefa
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState
                        icon={CheckCircle2}
                        title="Está tudo em dia"
                        tone="success"
                        description="Quando uma campanha precisar de uma decisão sua, a tarefa aparece aqui."
                    />
                )}
            </OperationalPanel>
            <KpiStrip
                items={[
                    {
                        label: 'Campanhas em curso',
                        tone: 'brand',
                        icon: 'campaign',
                        value: data.metrics.active,
                    },
                    {
                        label: 'A aguardar propostas',
                        tone: 'info',
                        icon: 'proposal',
                        value: data.metrics.awaitingProposals,
                    },
                    {
                        label: 'Pagamentos a decorrer',
                        tone: 'warning',
                        icon: 'money',
                        value: data.metrics.payments,
                    },
                    {
                        label: 'Entregas pendentes',
                        tone: 'success',
                        icon: 'delivery',
                        value: data.metrics.deliveries,
                    },
                ]}
            />
            <section className="min-w-0">
                <div className="mb-4 flex items-center justify-between gap-4">
                    <h2>As minhas campanhas</h2>
                    <Link
                        href={index()}
                        className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary"
                    >
                        Ver todas
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                </div>
                {data.campaigns.length ? (
                    <OrganizerCampaignTable records={data.campaigns} />
                ) : (
                    <EmptyState
                        icon={Plus}
                        title="A sua primeira campanha começa aqui"
                        description="Defina um produto e reúna pessoas interessadas na mesma compra."
                        actionHref={criar.url()}
                        actionLabel="Criar a primeira campanha"
                    />
                )}
            </section>
        </PageShell>
    );
}
