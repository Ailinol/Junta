import { EmptyState } from '@/components/junta/EmptyState';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { DetailList, Notice, Page } from '@/components/junta/Page';
import { StatusBadge } from '@/components/junta/StatusBadge';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { campaignIdFromUrl } from '@/lib/campaign-route';
import { formatDate } from '@/lib/formatters';
import { index } from '@/routes/fornecedor/oportunidades';
import { criar } from '@/routes/fornecedor/propostas';
import { Link, usePage } from '@inertiajs/react';
export default function Opportunity() {
    const { data } = usePrototypeStore();
    const { url } = usePage();
    const c = data.campaigns.find((c) => c.id === campaignIdFromUrl(url, 0));
    if (!c)
        return (
            <Page title="Oportunidade indisponível">
                <EmptyState
                    title="Oportunidade não encontrada"
                    description="Consulte as oportunidades disponíveis."
                    actionHref={index.url()}
                    actionLabel="Ver oportunidades"
                />
            </Page>
        );
    const open = data.supplierProfile.verified && c.acceptsProposals === true;
    return (
        <Page
            title={c.title}
            back={{ href: index.url(), label: 'Oportunidades' }}
        >
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <OperationalPanel title="O que se pretende comprar">
                    <CampaignVisual campaign={c} className="mb-5" />
                    <div className="mb-5">
                        <StatusBadge
                            status={c.status}
                            stage={c.effectiveStage}
                        />
                    </div>
                    <p className="mb-6 text-sm leading-7 text-muted-foreground">
                        {c.description}
                    </p>
                    <DetailList
                        items={[
                            {
                                label: 'Procura actual',
                                value: c.currentQuantity + ' ' + c.unit,
                            },
                            {
                                label: 'Objectivo de procura',
                                value: c.targetQuantity + ' ' + c.unit,
                            },
                            { label: 'Localização', value: c.location },
                            {
                                label: 'Modalidade solicitada',
                                value:
                                    c.deliveryMode === 'ambos'
                                        ? 'Entrega ou recolha'
                                        : c.deliveryMode,
                            },
                        ]}
                    />
                </OperationalPanel>
                <OperationalPanel title="Preparar resposta">
                    <DetailList
                        items={[
                            { label: 'Organizador', value: c.organizerName },
                            {
                                label: 'Prazo da campanha',
                                value: formatDate(c.deadline),
                            },
                        ]}
                    />
                    <Notice>
                        O modelo actual não define um prazo separado para
                        propostas. Consulte as condições da oportunidade.
                    </Notice>
                    {open ? (
                        <Link
                            href={criar({ query: { campaignId: c.id } })}
                            className="junta-btn junta-btn-md w-full junta-btn-primary"
                        >
                            Preparar proposta
                        </Link>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            O fornecedor precisa de estar verificado e a
                            oportunidade aberta para submeter propostas.
                        </p>
                    )}
                </OperationalPanel>
            </div>
        </Page>
    );
}
