import { Link } from '@inertiajs/react';
import { Page, Notice } from '@/components/junta/Page';
import { MetricStrip } from '@/components/junta/PurchasePresentation';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { RecordTable } from '@/components/junta/RecordTable';
import { LifecycleAction } from '@/components/junta/LifecycleAction';
import {
    edit,
    withdraw,
    show as proposalDetail,
} from '@/routes/fornecedor/propostas';
import { StatusBadge } from '@/components/junta/StatusBadge';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { formatDate, formatMZN } from '@/lib/formatters';
import { index as opportunities } from '@/routes/fornecedor/oportunidades';
import { show } from '@/routes/fornecedor/oportunidades';
export default function Proposals() {
    const { data } = usePrototypeStore();
    const ownProposals = data.proposals.filter(
        (proposal) => proposal.supplierId === data.supplierProfile.id,
    );
    return (
        <Page
            title="As minhas propostas"
            actions={
                <Link
                    href={opportunities()}
                    className="junta-btn junta-btn-md junta-btn-primary"
                >
                    Ver oportunidades
                </Link>
            }
        >
            {!data.supplierProfile.verified && (
                <Notice>
                    A verificação comercial está pendente. Pode consultar as
                    propostas, mas precisa de aprovação administrativa para
                    submeter uma proposta.
                </Notice>
            )}
            <MetricStrip
                items={[
                    {
                        label: 'Submetidas / em análise',
                        value: ownProposals.filter((p) =>
                            ['submetida', 'em_avaliacao'].includes(p.status),
                        ).length,
                    },
                    {
                        label: 'Ofertas seleccionadas',
                        value: ownProposals.filter((p) => p.status === 'aceite')
                            .length,
                    },
                ]}
            />
            <RecordTable
                records={[...ownProposals].sort(
                    (a, b) =>
                        Number(b.status === 'aceite') -
                        Number(a.status === 'aceite'),
                )}
                label="propostas"
                visual={(p) => (
                    <CampaignVisual campaign={p.campaign} thumbnail />
                )}
                searchText={(p) => p.campaign.title + ' ' + p.supplierName}
                filters={[
                    { value: 'rascunho', label: 'Rascunho' },
                    { value: 'submetida', label: 'Submetida' },
                    { value: 'em_avaliacao', label: 'Em avaliação' },
                    { value: 'aceite', label: 'Aceite' },
                    { value: 'recusada', label: 'Recusada' },
                    { value: 'expirada', label: 'Expirada' },
                    { value: 'retirada', label: 'Retirada pelo fornecedor' },
                ]}
                matchesFilter={(p, f) => p.status === f}
                actions={(proposal) =>
                    proposal.status === 'aceite' ? (
                        <Link
                            href={proposalDetail(proposal.id)}
                            className="junta-btn junta-btn-sm junta-btn-outline"
                        >
                            Ver proposta seleccionada
                        </Link>
                    ) : proposal.campaign.acceptsProposals &&
                      ['rascunho', 'submetida', 'em_avaliacao'].includes(
                          proposal.status,
                      ) ? (
                        <>
                            <Link
                                href={edit(proposal.id)}
                                className="junta-btn junta-btn-sm junta-btn-outline"
                            >
                                Editar proposta
                            </Link>
                            <LifecycleAction
                                action={withdraw(proposal.id)}
                                label="Retirar proposta"
                                description="A proposta deixa de estar disponível para selecção. O histórico é preservado e o organizador recebe o motivo."
                            />
                        </>
                    ) : null
                }
                columns={[
                    {
                        label: 'Campanha',
                        render: (p) => (
                            <div>
                                <span className="font-semibold">
                                    {p.campaign.title}
                                </span>
                                <p className="text-xs text-muted-foreground">
                                    {p.supplierName}
                                </p>
                            </div>
                        ),
                    },
                    {
                        label: 'Estado',
                        render: (p) => (
                            <StatusBadge status={p.status} type="proposal" />
                        ),
                    },
                    {
                        label: 'Preço unitário',
                        render: (p) => formatMZN(p.unitPrice),
                    },
                    {
                        label: 'Quantidade disponível',
                        render: (p) =>
                            p.availableQuantity + ' ' + p.campaign.unit,
                    },
                    {
                        label: 'Validade',
                        render: (p) => formatDate(p.validUntil),
                    },
                ]}
                details={(p) => (
                    <>
                        {p.status === 'aceite' && (
                            <Link
                                href={proposalDetail(p.id)}
                                className="mb-3 inline-block font-semibold text-primary underline"
                            >
                                Ver proposta e fornecimento
                            </Link>
                        )}
                        <p>Total declarado: {formatMZN(p.totalEstimated)}</p>
                        <p>
                            Fornecimento: {p.deliveryDays} dias ·{' '}
                            {p.deliveryMode}
                        </p>
                        <p>Submetida em: {formatDate(p.submittedAt)}</p>
                        <p className="whitespace-pre-wrap">
                            {p.notes || 'Sem observações adicionais.'}
                        </p>
                        <Link
                            href={show(p.campaignId)}
                            className="inline-flex text-primary"
                        >
                            Consultar oportunidade
                        </Link>
                    </>
                )}
            />
        </Page>
    );
}
