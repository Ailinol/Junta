import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { Page } from '@/components/junta/Page';
import { StatusBadge } from '@/components/junta/StatusBadge';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { formatDate, formatMZN } from '@/lib/formatters';
import { campaignTitle, campaignUnit } from '@/lib/campaign-presentation';
import { index, show } from '@/routes/fornecedor/oportunidades';
import {
    index as proposals,
    show as proposalDetail,
} from '@/routes/fornecedor/propostas';
import { Link } from '@inertiajs/react';
import { BriefcaseBusiness, FileText, CheckCheck, Clock3 } from 'lucide-react';
import { OverviewStats } from '@/components/junta/OverviewStats';
export default function Home() {
    const { data } = usePrototypeStore();
    const opportunities = data.campaigns.filter((c) => c.acceptsProposals);
    const ownProposals = data.proposals.filter(
        (proposal) => proposal.supplierId === data.supplierProfile.id,
    );
    return (
        <Page
            title="Oportunidades e propostas"
            description="Encontre procura reunida e acompanhe as condições que apresentou."
            actions={
                <Link
                    href={index()}
                    className="junta-btn junta-btn-md junta-btn-primary"
                >
                    Explorar oportunidades
                </Link>
            }
        >
            <OverviewStats
                items={[
                    {
                        label: 'Oportunidades',
                        value: opportunities.length,
                        description: 'Campanhas a consultar',
                        icon: BriefcaseBusiness,
                    },
                    {
                        label: 'As suas propostas',
                        value: ownProposals.length,
                        description: 'Histórico de propostas',
                        icon: FileText,
                    },
                    {
                        label: 'Seleccionadas',
                        value: ownProposals.filter((p) => p.status === 'aceite')
                            .length,
                        description: 'Propostas escolhidas',
                        icon: CheckCheck,
                    },
                    {
                        label: 'Em análise',
                        value: ownProposals.filter((p) =>
                            ['submetida', 'em_avaliacao'].includes(p.status),
                        ).length,
                        description: 'A aguardar decisão',
                        icon: Clock3,
                    },
                ]}
            />
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <OperationalPanel
                    title="Oportunidades a consultar"
                    action={{ label: 'Ver todas', href: index.url() }}
                >
                    {opportunities.length ? (
                        <ul className="divide-y divide-border">
                            {opportunities.slice(0, 5).map((c) => (
                                <li
                                    key={c.id}
                                    className="py-5 first:pt-0 last:pb-0"
                                >
                                    <div className="flex items-start gap-3">
                                        <CampaignVisual
                                            campaign={c}
                                            thumbnail
                                        />
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={show(c.id)}
                                                className="font-semibold hover:text-primary"
                                            >
                                                {campaignTitle(c.title)}
                                            </Link>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {c.currentQuantity} {c.unit} de
                                                procura · {c.location}
                                            </p>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Prazo da campanha:{' '}
                                                {formatDate(c.deadline)}
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Sem oportunidades disponíveis.
                        </p>
                    )}
                </OperationalPanel>
                <OperationalPanel
                    title="Propostas em acompanhamento"
                    action={{ label: 'Ver todas', href: proposals.url() }}
                >
                    {ownProposals.length ? (
                        <ul className="divide-y divide-border">
                            {ownProposals.slice(0, 4).map((p) => (
                                <li
                                    key={p.id}
                                    className="py-4 first:pt-0 last:pb-0"
                                >
                                    <p className="text-sm font-semibold">
                                        {p.campaign.title}
                                    </p>
                                    <p className="my-2 text-sm text-muted-foreground">
                                        {formatMZN(p.unitPrice)} /{' '}
                                        {campaignUnit(p.campaign.unit)}
                                    </p>
                                    <StatusBadge
                                        status={p.status}
                                        type="proposal"
                                    />
                                    {p.status === 'aceite' && (
                                        <Link
                                            href={proposalDetail(p.id)}
                                            className="mt-2 block text-primary underline"
                                        >
                                            Ver proposta seleccionada
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Ainda não existem propostas.
                        </p>
                    )}
                </OperationalPanel>
            </div>
        </Page>
    );
}
