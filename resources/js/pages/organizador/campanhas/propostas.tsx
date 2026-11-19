import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AiReviewPanel } from '@/components/junta/AiReviewPanel';
import { CampaignManagementLayout } from '@/components/junta/CampaignManagementLayout';
import { EmptyState } from '@/components/junta/EmptyState';
import { ConfirmDialog, DeadlineLabel } from '@/components/junta/OrganizerUi';
import { StatusPill } from '@/components/junta/StatusBadge';
import { money } from '@/components/junta/PurchaseForms';
import { mutate, errorMessage } from '@/lib/server-mutation';
import { seleccionar } from '@/routes/organizador/campanhas';
import { show as consumerCampaign } from '@/routes/consumidor/campanhas';
import type { OrganizerCampaign, OrganizerOffer } from '@/types/organizer';
export default function Compare() {
    const { organizerCampaign: view, organizerOffers: offers } = usePage<{
        organizerCampaign: OrganizerCampaign;
        organizerOffers: OrganizerOffer[];
    }>().props;
    const [selected, setSelected] = useState<OrganizerOffer | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const choose = async () => {
        if (!selected) return;
        setProcessing(true);
        setError('');
        try {
            await mutate(
                seleccionar({
                    campaign: view.campaign.id,
                    proposal: selected.id,
                }),
            );
            setSelected(null);
        } catch (failure) {
            setError(errorMessage(failure));
        } finally {
            setProcessing(false);
        }
    };
    return (
        <CampaignManagementLayout view={view} section="propostas">
            {!offers.length ? (
                <EmptyState
                    title="Ainda não há propostas"
                    description="Os fornecedores verificados podem enviar propostas enquanto a campanha estiver aberta. Acompanhe esta página para comparar as condições."
                    {...(['interest', 'evaluation'].includes(view.state.code)
                        ? {
                              actionLabel: 'Ver campanha publicada',
                              actionHref: consumerCampaign.url(
                                  view.campaign.id,
                              ),
                          }
                        : {})}
                />
            ) : (
                <>
                    <div
                        className="organizer-offers"
                        data-many={offers.length > 3 || undefined}
                    >
                        {offers.map((offer) => (
                            <article
                                key={offer.id}
                                className="organizer-offer"
                                data-selected={offer.selected || undefined}
                            >
                                <header className="grid gap-3">
                                    <StatusPill state={offer.state} />
                                    <h2>{offer.supplierName}</h2>
                                    <p className="organizer-offer-price">
                                        {money(offer.unitCents)}
                                        <small>por {view.campaign.unit}</small>
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {offer.bestPrice && (
                                            <span className="organizer-highlight">
                                                Melhor preço
                                            </span>
                                        )}
                                        {offer.fastest && (
                                            <span className="organizer-highlight">
                                                Menor prazo
                                            </span>
                                        )}
                                    </div>
                                </header>
                                <dl className="organizer-summary">
                                    {[
                                        {
                                            label: 'Total para a procura actual',
                                            value:
                                                offer.totalCents === null
                                                    ? 'Não indicado'
                                                    : money(offer.totalCents),
                                        },
                                        {
                                            label: 'Quantidade disponível',
                                            value:
                                                offer.quantity == null
                                                    ? 'Não indicado'
                                                    : `${offer.quantity} ${view.campaign.unit}`,
                                        },
                                        {
                                            label: 'Mínimo',
                                            value:
                                                offer.minimum == null
                                                    ? 'Não indicado'
                                                    : `${offer.minimum} ${view.campaign.unit}`,
                                        },
                                        {
                                            label: 'Prazo de entrega',
                                            value:
                                                offer.deliveryDays == null
                                                    ? 'Não indicado'
                                                    : `${offer.deliveryDays} dias`,
                                        },
                                        {
                                            label: 'Transporte por unidade',
                                            value:
                                                offer.transportIncluded === true
                                                    ? 'Incluído'
                                                    : offer.transportCents ==
                                                        null
                                                      ? 'Não indicado'
                                                      : money(
                                                            offer.transportCents,
                                                        ),
                                        },
                                        {
                                            label: 'Como receber',
                                            value:
                                                offer.deliveryLabel ||
                                                'Não indicado',
                                        },
                                        {
                                            label: 'Cobertura',
                                            value: offer.coverage,
                                        },
                                    ].map((row) => (
                                        <div key={row.label}>
                                            <dt>{row.label}</dt>
                                            <dd>{row.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                                <DeadlineLabel deadline={offer.validity} />
                                <details>
                                    <summary>Condições adicionais</summary>
                                    <p className="mt-3 whitespace-pre-wrap">
                                        {offer.notes || 'Não indicado'}
                                    </p>
                                </details>
                                {offer.canSelect && !view.state.isFinal ? (
                                    <button
                                        className="junta-btn mt-auto junta-btn-md junta-btn-primary"
                                        onClick={() => {
                                            setError('');
                                            setSelected(offer);
                                        }}
                                    >
                                        Escolher esta proposta
                                    </button>
                                ) : (
                                    offer.blockedReason && (
                                        <p className="organizer-caption mt-auto">
                                            {offer.blockedReason}
                                        </p>
                                    )
                                )}
                            </article>
                        ))}
                    </div>
                    <details className="organizer-technical">
                        <summary>
                            Comparação de IA · apoio consultivo opcional
                        </summary>
                        <p className="my-4">
                            A decisão continua a ser sua. A análise indica a
                            validade e avisa quando as condições mudarem.
                        </p>
                        <AiReviewPanel
                            kind="comparison"
                            payload={{}}
                            campaignId={view.campaign.id}
                            selectableProposalIds={offers
                                .filter(
                                    (offer) =>
                                        offer.canSelect && !view.state.isFinal,
                                )
                                .map((offer) => offer.id)}
                            onChooseProposal={(proposalId) => {
                                const offer = offers.find(
                                    (item) => item.id === proposalId,
                                );
                                if (offer?.canSelect && !view.state.isFinal) {
                                    setError('');
                                    setSelected(offer);
                                }
                            }}
                            sourceVersion={JSON.stringify(offers)}
                            proposalNames={Object.fromEntries(
                                offers.map((offer) => [
                                    offer.id,
                                    offer.supplierName,
                                ]),
                            )}
                        />
                    </details>
                    <p className="organizer-caption">
                        O total estimado apresenta o produto para a procura
                        actual. Transporte, comissão e entrega são definidos nas
                        condições finais.
                    </p>
                </>
            )}
            <ConfirmDialog
                open={!!selected}
                onOpenChange={(open) => !open && setSelected(null)}
                title={`Escolher ${selected?.supplierName ?? 'esta proposta'}?`}
                description="As restantes propostas serão recusadas e o fornecedor escolhido será notificado. As condições desta proposta ficam fixadas. A seguir, poderá pedir os pagamentos aos participantes."
                confirmLabel="Confirmar escolha"
                processing={processing}
                onConfirm={() => void choose()}
            >
                <p>
                    {selected && money(selected.unitCents)} por unidade ·{' '}
                    {selected?.quantity} unidades disponíveis.
                </p>
                {error && (
                    <p role="alert" className="text-destructive">
                        {error}
                    </p>
                )}
            </ConfirmDialog>
        </CampaignManagementLayout>
    );
}
