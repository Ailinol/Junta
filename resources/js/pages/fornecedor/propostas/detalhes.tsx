import { Form, Link } from '@inertiajs/react';
import { Page, Notice, DetailList } from '@/components/junta/Page';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { StatusBadge } from '@/components/junta/StatusBadge';
import { EmptyState } from '@/components/junta/EmptyState';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { formatDate, formatMZN } from '@/lib/formatters';
import { campaignTitle } from '@/lib/campaign-presentation';
import { index, edit, problem } from '@/routes/fornecedor/propostas';

export default function ProposalDetail({
    proposalId,
    supply,
}: {
    proposalId: number;
    supply: {
        quantity: number;
        received_quantity: number;
        status: string;
    } | null;
}) {
    const { data } = usePrototypeStore();
    const proposal = data.proposals.find(
        (record) =>
            record.id === proposalId &&
            record.supplierId === data.supplierProfile.id,
    );
    if (!proposal)
        return (
            <Page title="Proposta">
                <EmptyState
                    title="Proposta indisponível"
                    description="Consulte as propostas do seu perfil."
                    actionHref={index.url()}
                    actionLabel="As minhas propostas"
                />
            </Page>
        );
    const selected = proposal.status === 'aceite';
    const editable =
        data.supplierProfile.verified &&
        proposal.campaign.acceptsProposals &&
        ['rascunho', 'submetida', 'em_avaliacao'].includes(proposal.status);
    return (
        <Page
            title={campaignTitle(proposal.campaign.title)}
            description="Condições da sua proposta"
            back={{ href: index.url(), label: 'As minhas propostas' }}
            badge={<StatusBadge status={proposal.status} type="proposal" />}
            actions={
                editable && (
                    <Link
                        className="junta-btn junta-btn-md junta-btn-outline"
                        href={edit(proposal.id)}
                    >
                        Editar proposta
                    </Link>
                )
            }
        >
            {selected && (
                <Notice>
                    A proposta foi seleccionada e estas condições são o seu
                    compromisso. Já não pode alterá-las.
                </Notice>
            )}
            {!selected && !editable && (
                <Notice>
                    Esta proposta está encerrada e só pode ser consultada.
                </Notice>
            )}
            <div className="grid items-start gap-6 lg:grid-cols-2">
                <OperationalPanel title="Condições comerciais">
                    <DetailList
                        items={[
                            {
                                label: 'Preço unitário',
                                value: formatMZN(proposal.unitPrice),
                            },
                            {
                                label: 'Quantidade disponível',
                                value: `${proposal.availableQuantity} ${proposal.campaign.unit}`,
                            },
                            {
                                label: 'Mínimo comercial',
                                value:
                                    proposal.minimumQuantity == null
                                        ? 'Não indicado'
                                        : `${proposal.minimumQuantity} ${proposal.campaign.unit}`,
                            },
                            {
                                label: 'Prazo de fornecimento',
                                value: `${proposal.deliveryDays} dias`,
                            },
                            {
                                label: 'Validade',
                                value: formatDate(proposal.validUntil),
                            },
                            {
                                label: 'Reserva',
                                value: proposal.reservedUntil
                                    ? formatDate(proposal.reservedUntil)
                                    : 'Não indicada',
                            },
                            {
                                label: 'Transporte por unidade',
                                value: proposal.transportIncluded
                                    ? 'Incluído'
                                    : proposal.transportUnitPrice == null
                                      ? 'Não indicado'
                                      : formatMZN(proposal.transportUnitPrice),
                            },
                            {
                                label: 'Modalidade',
                                value:
                                    proposal.deliveryMode === 'ambos'
                                        ? 'Entrega ou recolha'
                                        : proposal.deliveryMode === 'entrega'
                                          ? 'Entrega'
                                          : 'Recolha',
                            },
                        ]}
                    />
                    {proposal.notes && (
                        <p className="mt-5 text-sm whitespace-pre-wrap">
                            {proposal.notes}
                        </p>
                    )}
                </OperationalPanel>
                {selected && (
                    <div className="space-y-6">
                        {supply && (
                            <OperationalPanel title="Fornecimento">
                                <DetailList
                                    items={[
                                        {
                                            label: 'Quantidade efectiva',
                                            value: `${supply.quantity} ${proposal.campaign.unit}`,
                                        },
                                        {
                                            label: 'Recepção registada',
                                            value: `${supply.received_quantity} ${proposal.campaign.unit}`,
                                        },
                                        {
                                            label: 'Estado',
                                            value:
                                                supply.received_quantity >=
                                                supply.quantity
                                                    ? 'Mercadoria recebida'
                                                    : supply.received_quantity >
                                                        0
                                                      ? 'Recepção parcial'
                                                      : 'Mercadoria a fornecer',
                                        },
                                    ]}
                                />
                            </OperationalPanel>
                        )}
                        <OperationalPanel title="Se não conseguir cumprir">
                            <details>
                                <summary>Comunicar um problema</summary>
                                <p className="my-4 text-sm text-muted-foreground">
                                    O organizador recebe o motivo e decide como
                                    prosseguir; comunicar não altera a campanha
                                    nem os pagamentos.
                                </p>
                                <Form
                                    action={problem.url(proposal.id)}
                                    method="post"
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({
                                        errors,
                                        processing,
                                        wasSuccessful,
                                    }) => (
                                        <>
                                            <label className="grid gap-2 text-sm">
                                                Motivo
                                                <select
                                                    name="reason"
                                                    className="junta-input"
                                                    required
                                                    disabled={processing}
                                                    aria-invalid={
                                                        !!errors.reason
                                                    }
                                                >
                                                    <option value="">
                                                        Escolha o motivo
                                                    </option>
                                                    <option value="stock">
                                                        Disponibilidade da
                                                        mercadoria
                                                    </option>
                                                    <option value="deadline">
                                                        Prazo de entrega
                                                    </option>
                                                    <option value="transport">
                                                        Transporte
                                                    </option>
                                                    <option value="other">
                                                        Outro motivo
                                                    </option>
                                                </select>
                                                {errors.reason && (
                                                    <span
                                                        role="alert"
                                                        className="text-destructive"
                                                    >
                                                        {errors.reason}
                                                    </span>
                                                )}
                                            </label>
                                            <label className="grid gap-2 text-sm">
                                                Descreva o problema
                                                <textarea
                                                    name="description"
                                                    className="junta-input min-h-28"
                                                    maxLength={2000}
                                                    required
                                                    disabled={processing}
                                                    aria-invalid={
                                                        !!errors.description
                                                    }
                                                />
                                                {errors.description && (
                                                    <span
                                                        role="alert"
                                                        className="text-destructive"
                                                    >
                                                        {errors.description}
                                                    </span>
                                                )}
                                            </label>
                                            {errors.proposal && (
                                                <p
                                                    role="alert"
                                                    className="text-sm text-destructive"
                                                >
                                                    {errors.proposal}
                                                </p>
                                            )}
                                            {wasSuccessful && (
                                                <p
                                                    role="status"
                                                    className="text-sm"
                                                >
                                                    O organizador foi
                                                    notificado.
                                                </p>
                                            )}
                                            <button
                                                disabled={processing}
                                                type="submit"
                                                className="junta-btn junta-btn-md junta-btn-primary"
                                            >
                                                {processing
                                                    ? 'A enviar…'
                                                    : 'Comunicar problema'}
                                            </button>
                                        </>
                                    )}
                                </Form>
                            </details>
                        </OperationalPanel>
                    </div>
                )}
            </div>
        </Page>
    );
}
