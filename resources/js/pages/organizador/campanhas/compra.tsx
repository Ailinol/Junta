import { router, usePoll } from '@inertiajs/react';
import { CampaignManagementLayout } from '@/components/junta/CampaignManagementLayout';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { OpenPaymentForm } from '@/components/junta/OpenPaymentForm';
import {
    DataTable,
    KpiStrip,
    ProgressMeter,
    TechnicalDetails,
    type DataColumn,
} from '@/components/junta/OrganizerUi';
import { EmptyState } from '@/components/junta/EmptyState';
import { StatusPill } from '@/components/junta/StatusBadge';
import {
    PurchaseForm,
    PurchasePagination,
    money,
} from '@/components/junta/PurchaseForms';
import { edit, propostas } from '@/routes/organizador/campanhas';
import {
    show,
    closeInterest,
    close,
    receive,
} from '@/routes/organizador/compra';
import { prepare, handoff } from '@/routes/organizador/pedidos';
import type { OrganizerCampaign, OrganizerOrder } from '@/types/organizer';
import type {
    Purchase,
    PurchasePage,
    PurchaseOperations as Operations,
} from '@/types/purchase';
export default function PurchaseOperations({
    purchase,
    orders,
    operations,
    organizerCampaign: view,
    orderFilter,
}: {
    purchase: Purchase;
    orders: PurchasePage<OrganizerOrder>;
    operations: Operations;
    organizerCampaign: OrganizerCampaign;
    orderFilter: string;
}) {
    usePoll(10000, {
        only: ['purchase', 'orders', 'operations', 'organizerCampaign'],
        async: true,
        showProgress: false,
    });
    const phase = view.state.code;
    const payments = ['payment', 'review'].includes(phase);
    const supply = phase === 'supply';
    const orderColumns: DataColumn<OrganizerOrder>[] = [
        {
            key: 'name',
            label: 'Participante',
            render: (order) => (
                <strong id={'order-' + order.id}>
                    {order.participantName}
                </strong>
            ),
        },
        {
            key: 'quantity',
            label: 'Quantidade',
            render: (order) => `${order.quantity} ${view.campaign.unit}`,
        },
        {
            key: 'value',
            label: 'Valor',
            render: (order) => money(order.total_cents),
        },
        {
            key: 'state',
            label: 'Pagamento',
            render: (order) => <StatusPill state={order.state} />,
        },
    ];
    const orderList = (
        <>
            <DataTable
                records={orders.data}
                columns={orderColumns}
                label={
                    view.state.isFinal
                        ? 'Resultado dos pedidos'
                        : 'Pagamentos dos participantes'
                }
                rowKey={(order) => order.id}
                rowState={(order) => order.state}
                empty={
                    <EmptyState
                        title="Ainda não há pedidos"
                        description="Os pedidos dos participantes aparecerão aqui quando os pagamentos forem abertos."
                    />
                }
            />
            <PurchasePagination page={orders} />
        </>
    );
    return (
        <CampaignManagementLayout
            view={view}
            section="compra"
            testPayment={purchase.testTools}
        >
            {phase === 'draft' && (
                <EmptyState
                    title="Prepare a campanha para publicar"
                    description="Complete o produto e as condições. Depois poderá reunir o interesse dos participantes."
                    actionLabel="Continuar preparação"
                    actionHref={edit.url(purchase.id)}
                />
            )}
            {phase === 'interest' && (
                <OperationalPanel title="Procura do grupo" emphasis="brand">
                    <div className="grid gap-6" id="current-task">
                        <ProgressMeter
                            {...view.progress}
                            deadline={view.deadline}
                            goalLabel={
                                ['payment', 'review'].includes(view.state.code)
                                    ? 'Mínimo'
                                    : view.state.code === 'supply'
                                      ? 'Encomenda'
                                      : 'Meta'
                            }
                        />
                        <p className="text-muted-foreground">
                            Ao fechar o interesse, passa a comparar propostas.
                            Ainda não será cobrado nenhum pagamento.
                        </p>
                        <PurchaseForm
                            action={closeInterest(purchase.id)}
                            label="Fechar interesse e avaliar propostas"
                        />
                    </div>
                </OperationalPanel>
            )}
            {phase === 'evaluation' && (
                <EmptyState
                    title={
                        view.metrics.openProposals
                            ? 'Compare as propostas recebidas'
                            : 'A aguardar propostas'
                    }
                    description={
                        view.metrics.openProposals
                            ? 'Compare preço, quantidade e prazo antes de escolher o fornecedor.'
                            : 'Os fornecedores verificados podem apresentar as suas condições nesta etapa.'
                    }
                    actionLabel="Consultar propostas"
                    actionHref={propostas.url(purchase.id)}
                />
            )}
            {['selection', 'confirmed'].includes(phase) &&
                view.state.primaryAction?.code === 'open_payments' && (
                    <OpenPaymentForm purchase={purchase} />
                )}
            {payments && (
                <>
                    <OperationalPanel
                        title="Pagamentos dos participantes"
                        emphasis="brand"
                    >
                        <div className="grid gap-6">
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
                            {operations.canApure && (
                                <div id="current-task">
                                    <p className="mb-4 text-muted-foreground">
                                        O prazo terminou e a verificação
                                        automática ainda não decidiu a compra.
                                        Pode iniciá-la agora como contingência.
                                    </p>
                                    <PurchaseForm
                                        action={close(purchase.id)}
                                        label="Verificar o mínimo agora"
                                    />
                                </div>
                            )}
                        </div>
                    </OperationalPanel>
                    {orderList}
                </>
            )}
            {supply && (
                <>
                    <div className="organizer-distribution" id="current-task">
                        <aside className="grid content-start gap-6">
                            <OperationalPanel title="Mercadoria do fornecedor">
                                <p className="font-semibold">
                                    {view.supplierName ??
                                        'Fornecedor escolhido'}
                                </p>
                                <dl className="organizer-summary mt-4">
                                    <div>
                                        <dt>Quantidade encomendada</dt>
                                        <dd>
                                            {purchase.supply?.quantity ??
                                                view.metrics.chargedUnits}{' '}
                                            {view.campaign.unit}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Valor do produto e transporte</dt>
                                        <dd>
                                            {money(view.metrics.supplierCents)}
                                        </dd>
                                    </div>
                                </dl>
                                {purchase.supply && (
                                    <TechnicalDetails>
                                        <p>
                                            Referência da encomenda:{' '}
                                            {purchase.supply.reference}
                                        </p>
                                    </TechnicalDetails>
                                )}
                            </OperationalPanel>
                            <OperationalPanel title="Receber mercadoria">
                                <p className="mb-4">
                                    {view.metrics.receivedUnits} de{' '}
                                    {purchase.supply?.quantity ??
                                        view.metrics.chargedUnits}{' '}
                                    unidades recebidas.
                                </p>
                                {operations.canReceive && (
                                    <details
                                        open={view.metrics.receivedUnits === 0}
                                    >
                                        <summary className="font-semibold">
                                            Registar recepção
                                        </summary>
                                        <div className="mt-4">
                                            <PurchaseForm
                                                action={receive(purchase.id)}
                                                label="Guardar recepção"
                                                fields={[
                                                    {
                                                        name: 'receivedQuantity',
                                                        label: 'Quantidade total já recebida (acumulada)',
                                                        type: 'number',
                                                        value: view.metrics
                                                            .receivedUnits,
                                                    },
                                                    {
                                                        name: 'reference',
                                                        label: 'Número da guia',
                                                    },
                                                    {
                                                        name: 'reason',
                                                        label: 'Observações sobre a recepção',
                                                    },
                                                ]}
                                            />
                                        </div>
                                    </details>
                                )}
                            </OperationalPanel>
                            <KpiStrip
                                items={[
                                    {
                                        label: 'Recebidas',
                                        tone: 'info',
                                        icon: 'units',
                                        value: view.metrics.receivedUnits,
                                    },
                                    {
                                        label: 'Já separadas',
                                        tone: 'brand',
                                        icon: 'units',
                                        value: view.metrics.allocatedUnits,
                                    },
                                    {
                                        label: 'Disponíveis',
                                        tone: 'success',
                                        icon: 'delivery',
                                        value: view.metrics.unallocatedUnits,
                                    },
                                ]}
                            />
                        </aside>
                        <section
                            className="grid min-w-0 content-start gap-4"
                            id="purchase-orders"
                        >
                            <h2>Pedidos dos participantes</h2>
                            <label className="organizer-field">
                                Mostrar pedidos
                                <select
                                    className="junta-input"
                                    value={orderFilter}
                                    onChange={(event) =>
                                        router.get(
                                            show.url(purchase.id),
                                            event.target.value
                                                ? {
                                                      receipt:
                                                          event.target.value,
                                                  }
                                                : {},
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                >
                                    <option value="">Todos</option>
                                    <option value="waiting">Por separar</option>
                                    <option value="ready">Prontos</option>
                                    <option value="received">Entregues</option>
                                </select>
                            </label>
                            {orders.data.length ? (
                                orders.data.map((order) => (
                                    <OperationalPanel
                                        key={order.id}
                                        emphasis={
                                            order.state.blocker
                                                ? 'warning'
                                                : order.state.code === 'issue'
                                                  ? 'danger'
                                                  : undefined
                                        }
                                        title={order.participantName}
                                    >
                                        <div
                                            id={'order-' + order.id}
                                            className="grid scroll-mt-24 gap-4"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <strong>
                                                    {order.quantity}{' '}
                                                    {view.campaign.unit} ·{' '}
                                                    {money(order.total_cents)}
                                                </strong>
                                                <StatusPill
                                                    state={order.state}
                                                />
                                            </div>
                                            <p className="text-muted-foreground">
                                                {order.delivery_method ===
                                                'entrega'
                                                    ? `Entrega: ${order.delivery_address}`
                                                    : `Recolha: ${purchase.config?.pickup_point}`}
                                            </p>
                                            {order.state.primaryAction?.code ===
                                                'prepare' && (
                                                <PurchaseForm
                                                    action={prepare(order.id)}
                                                    label="Separar pedido"
                                                    blockedReason={
                                                        order.state.blocker
                                                    }
                                                />
                                            )}{' '}
                                            {order.state.primaryAction?.code ===
                                                'handoff' && (
                                                <PurchaseForm
                                                    action={handoff(order.id)}
                                                    label="Confirmar entrega"
                                                    fields={[
                                                        {
                                                            name: 'code',
                                                            label: 'Código privado apresentado pelo participante',
                                                        },
                                                    ]}
                                                >
                                                    <p className="organizer-caption">
                                                        Introduza o código
                                                        apenas no momento da
                                                        entrega. Só pode ser
                                                        usado uma vez.
                                                    </p>
                                                </PurchaseForm>
                                            )}
                                            {order.state.code === 'issue' && (
                                                <p>
                                                    A administração acompanha o
                                                    problema com este pedido.
                                                </p>
                                            )}
                                            <TechnicalDetails>
                                                <p>
                                                    Referência do pedido:{' '}
                                                    {order.reference}
                                                </p>
                                            </TechnicalDetails>
                                        </div>
                                    </OperationalPanel>
                                ))
                            ) : (
                                <EmptyState
                                    title="Sem pedidos neste estado"
                                    description="Escolha outro estado para consultar os pedidos desta campanha."
                                    onAction={() =>
                                        router.get(
                                            show.url(purchase.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    actionLabel="Ver todos os pedidos"
                                />
                            )}
                            <PurchasePagination page={orders} />
                        </section>
                    </div>
                </>
            )}
            {view.state.isFinal && (
                <>
                    <OperationalPanel
                        title={
                            phase === 'completed'
                                ? 'Campanha concluída'
                                : 'Campanha cancelada'
                        }
                    >
                        <p>
                            {phase === 'cancelled' &&
                            view.metrics.chargedUnits > 0
                                ? 'A compra foi cancelada. Os valores cobrados são devolvidos integralmente; acompanhe o estado de cada participante abaixo.'
                                : 'Este é o resultado final da campanha. O histórico e os pedidos ficam disponíveis para consulta.'}
                        </p>
                    </OperationalPanel>
                    <KpiStrip
                        items={[
                            {
                                label: 'Unidades com pagamento',
                                tone: 'brand',
                                icon: 'units',
                                value: view.metrics.chargedUnits,
                            },
                            {
                                label: 'Total recebido',
                                tone: 'success',
                                icon: 'money',
                                value: money(view.metrics.receivedCents),
                            },
                            {
                                label: 'Pedidos entregues',
                                tone: 'success',
                                icon: 'delivery',
                                value: view.metrics.receivedOrders,
                            },
                            {
                                label: 'Pedidos reembolsados',
                                tone: 'neutral',
                                icon: 'refund',
                                value: view.metrics.refundedOrders,
                            },
                        ]}
                    />
                    {purchase.config && orderList}
                </>
            )}
        </CampaignManagementLayout>
    );
}
