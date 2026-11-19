import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import {
    DataTable,
    DeadlineLabel,
    ProgressMeter,
} from '@/components/junta/OrganizerUi';
import { StatusPill } from '@/components/junta/StatusBadge';
import { organizerActionUrl } from '@/lib/organizer-routes';
import { show } from '@/routes/organizador/campanhas';
import type { OrganizerCampaign } from '@/types/organizer';

export function OrganizerCampaignTable({
    records,
}: {
    records: OrganizerCampaign[];
}) {
    return (
        <DataTable
            label="Campanhas do organizador"
            records={records}
            rowKey={(view) => view.campaign.id}
            rowState={(view) => view.state}
            rowHref={(view) => show.url(view.campaign.id)}
            columns={[
                {
                    key: 'campaign',
                    label: 'Campanha',
                    render: (view) => (
                        <div className="flex min-w-0 items-center gap-3">
                            <CampaignVisual
                                campaign={view.campaign}
                                thumbnail
                            />
                            <div className="min-w-0">
                                <Link
                                    className="font-semibold text-foreground hover:text-primary"
                                    href={show(view.campaign.id)}
                                >
                                    {view.campaign.title}
                                </Link>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {view.campaign.reference} ·{' '}
                                    {view.campaign.location}
                                </p>
                            </div>
                        </div>
                    ),
                },
                {
                    key: 'phase',
                    label: 'Fase',
                    render: (view) => <StatusPill state={view.state} />,
                },
                {
                    key: 'progress',
                    label: 'Progresso',
                    render: (view) =>
                        view.state.isFinal ? (
                            <span className="text-muted-foreground">—</span>
                        ) : [
                              'interest',
                              'payment',
                              'review',
                              'supply',
                          ].includes(view.state.code) ? (
                            <ProgressMeter
                                {...view.progress}
                                deadline={view.deadline}
                                goalLabel={
                                    ['payment', 'review'].includes(
                                        view.state.code,
                                    )
                                        ? 'Mínimo'
                                        : view.state.code === 'supply'
                                          ? 'Encomenda'
                                          : 'Meta'
                                }
                            />
                        ) : (
                            <span className="text-sm">
                                {view.metrics.proposals} propostas
                            </span>
                        ),
                },
                {
                    key: 'deadline',
                    label: 'Prazo',
                    render: (view) =>
                        view.state.isFinal ? (
                            <span className="text-muted-foreground">—</span>
                        ) : view.deadline ? (
                            <DeadlineLabel deadline={view.deadline} />
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                {view.state.waitingOn?.label ?? 'Em preparação'}
                            </span>
                        ),
                },
                {
                    key: 'action',
                    label: 'O que fazer',
                    render: (view) =>
                        view.state.primaryAction ? (
                            <Link
                                href={organizerActionUrl(
                                    view.campaign.id,
                                    view.state.primaryAction,
                                )}
                                className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary"
                            >
                                {view.state.primaryAction.label}
                                <ArrowUpRight
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                            </Link>
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                {view.state.blocker ??
                                    (view.state.isFinal
                                        ? 'Só leitura'
                                        : view.state.waitingOn?.label)}
                            </span>
                        ),
                },
            ]}
        />
    );
}

export function CampaignListDeadline({ view }: { view: OrganizerCampaign }) {
    const deadline = view.list.deadline;
    return deadline ? (
        <time
            className="organizer-list-deadline"
            data-blocked={deadline.blocked || undefined}
            dateTime={deadline.value}
            title={deadline.detail}
        >
            {deadline.label}
        </time>
    ) : null;
}

function CampaignListTitle({ view }: { view: OrganizerCampaign }) {
    return (
        <div className="organizer-list-title">
            {view.campaign.imageUrl && (
                <CampaignVisual
                    campaign={view.campaign}
                    thumbnail
                    hideFallback
                    className="organizer-list-photo"
                />
            )}
            <Link href={show(view.campaign.id)} title={view.campaign.title}>
                {view.campaign.title}
            </Link>
        </div>
    );
}

function CampaignListProgress({ view }: { view: OrganizerCampaign }) {
    return view.list.progress ? (
        <ProgressMeter {...view.list.progress} compact />
    ) : (
        <span className="text-muted-foreground">—</span>
    );
}

export function CampaignListNextStep({ view }: { view: OrganizerCampaign }) {
    const { action, waiting } = view.list;
    if (view.list.section === 'attention' && action) {
        return (
            <Link
                className="organizer-list-action junta-btn junta-btn-sm junta-btn-outline"
                href={organizerActionUrl(view.campaign.id, action)}
            >
                {action.label}
            </Link>
        );
    }
    return waiting ? (
        <span className="organizer-list-waiting" title={waiting}>
            {waiting}
        </span>
    ) : null;
}

export function CompactCampaignTable({
    records,
}: {
    records: OrganizerCampaign[];
}) {
    return (
        <>
            <div className="organizer-compact-desktop">
                <table
                    className="organizer-compact-table"
                    aria-label="Campanhas do organizador"
                >
                    <colgroup>
                        <col style={{ width: '40%' }} />
                        <col style={{ width: '22%' }} />
                        <col style={{ width: '16%' }} />
                        <col style={{ width: '22%' }} />
                    </colgroup>
                    <thead>
                        <tr>
                            {[
                                'Campanha',
                                'Fase',
                                'Progresso',
                                'Próximo passo',
                            ].map((label) => (
                                <th key={label} scope="col">
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {records.map((view) => (
                            <tr
                                key={view.campaign.id}
                                data-section={view.list.section}
                            >
                                <td>
                                    <CampaignListTitle view={view} />
                                </td>
                                <td>
                                    <div className="organizer-list-phase">
                                        <StatusPill
                                            state={view.state}
                                            compact
                                        />
                                        <CampaignListDeadline view={view} />
                                    </div>
                                </td>
                                <td>
                                    <CampaignListProgress view={view} />
                                </td>
                                <td>
                                    <CampaignListNextStep view={view} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="organizer-compact-mobile">
                {records.map((view) => (
                    <article
                        className="organizer-list-card"
                        key={view.campaign.id}
                        data-section={view.list.section}
                    >
                        <CampaignListTitle view={view} />
                        <div className="organizer-list-mobile-phase">
                            <StatusPill state={view.state} compact />
                            <CampaignListDeadline view={view} />
                        </div>
                        <div className="organizer-list-mobile-bottom">
                            <CampaignListProgress view={view} />
                            <CampaignListNextStep view={view} />
                        </div>
                    </article>
                ))}
            </div>
        </>
    );
}
