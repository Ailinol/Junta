import { Link, router } from '@inertiajs/react';
import { useState, type ComponentProps } from 'react';
import { Page } from '@/components/junta/Page';
import { DataTable, ProgressMeter } from '@/components/junta/OrganizerUi';
import { StatusPill } from '@/components/junta/StatusBadge';
import { FilterBar, ActionBanner } from '@/components/junta/OrganizerConsole';
import { SegmentedControl } from '@/components/junta/ConsumerUi';
import {
    InterventionQueue,
    CampaignFinanceHeader,
    FinanceSummary,
    MovementsTable,
    ClosureChecklist,
    OrderDrawer,
    FinancialConfirmDialog,
    financeLabels,
} from '@/components/junta/AdminFinance';
import { money, PurchasePagination } from '@/components/junta/PurchaseForms';
import { index, exportMethod } from '@/routes/admin/compras';
import type { AdminFinanceProps, AdminOrder } from '@/types/admin';
export default function PurchaseFinance(props: AdminFinanceProps) {
    const {
        selectedView: view,
        selectedCampaignId: campaignId,
        tab,
        closure,
        selectedOperations: operations,
    } = props;
    const [query, setQuery] = useState(props.filters.search);
    const [phase, setPhase] = useState('');
    const [from, setFrom] = useState(props.filters.from ?? '');
    const [to, setTo] = useState(props.filters.to ?? '');
    const [type, setType] = useState(props.filters.type ?? '');
    const [order, setOrder] = useState<AdminOrder | null>(props.selectedOrder);
    const [operation, setOperation] = useState<
        ComponentProps<typeof FinancialConfirmDialog>['operation'] | null
    >(null);
    const visitTab = (value: string) =>
        router.get(
            index.url(),
            { campaignId, tab: value },
            { preserveScroll: true },
        );
    const apply = () =>
        router.get(
            index.url(),
            {
                campaignId,
                tab,
                search: query,
                from: from || null,
                to: to || null,
                type: type || null,
            },
            { preserveScroll: true },
        );
    const filtered = Boolean(
        props.filters.search ||
        props.filters.from ||
        props.filters.to ||
        props.filters.type,
    );
    const closed = view?.workspace.isFinal;
    const purchases = props.purchaseCampaigns.filter(
        (row) =>
            (!phase || row.workspace.code === phase) &&
            (row.campaign.title + ' ' + row.campaign.organizerName)
                .toLocaleLowerCase()
                .includes(query.toLocaleLowerCase()),
    );
    return (
        <Page
            title={view?.campaign.title ?? 'Compras e financeiro'}
            badge={view ? <CampaignFinanceHeader view={view} /> : undefined}
            description={
                view
                    ? undefined
                    : 'Supervisione as compras e trate das intervenções pendentes.'
            }
            breadcrumbs={
                view
                    ? [
                          { label: 'Compras e financeiro', href: index.url() },
                          { label: view.campaign.title },
                      ]
                    : undefined
            }
        >
            {props.testPayments && (
                <p className="admin-test-notice" role="note">
                    Ambiente de teste — os pagamentos usam o prestador de
                    testes.
                </p>
            )}
            {!view ? (
                <>
                    <section className="admin-panel">
                        <h2>Intervenções ({props.interventions.total})</h2>
                        <InterventionQueue page={props.interventions} />
                    </section>
                    <section className="admin-panel">
                        <h2>Campanhas com compra</h2>
                        <FilterBar>
                            <input
                                className="junta-input"
                                aria-label="Pesquisar compras"
                                placeholder="Campanha ou organizador"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                            />
                            <select
                                className="junta-input"
                                aria-label="Fase"
                                value={phase}
                                onChange={(event) =>
                                    setPhase(event.target.value)
                                }
                            >
                                <option value="">Todas as fases</option>
                                {[
                                    ...new Map(
                                        props.purchaseCampaigns.map((row) => [
                                            row.workspace.code,
                                            row.workspace.label,
                                        ]),
                                    ).entries(),
                                ].map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </FilterBar>
                        <DataTable
                            records={purchases}
                            rowKey={(row) => row.campaign.id}
                            label="Campanhas com compra"
                            rowHref={(row) =>
                                index.url({
                                    query: { campaignId: row.campaign.id },
                                })
                            }
                            columns={[
                                {
                                    key: 'campaign',
                                    label: 'Campanha',
                                    render: (row) => row.campaign.title,
                                },
                                {
                                    key: 'phase',
                                    label: 'Fase',
                                    render: (row) => (
                                        <StatusPill
                                            state={row.workspace}
                                            compact
                                        />
                                    ),
                                },
                                {
                                    key: 'progress',
                                    label: 'Recebido / mínimo',
                                    render: (row) => (
                                        <ProgressMeter
                                            compact
                                            value={row.metrics.paidUnits}
                                            target={row.purchaseMinimum}
                                            label="Quantidade paga / mínimo comercial"
                                        />
                                    ),
                                },
                                {
                                    key: 'balance',
                                    label: 'Saldo por liquidar',
                                    render: (row) =>
                                        money(row.balanceCents ?? 0),
                                },
                                {
                                    key: 'open',
                                    label: 'Consultar',
                                    render: (row) => (
                                        <Link
                                            href={index({
                                                query: {
                                                    campaignId: row.campaign.id,
                                                },
                                            })}
                                        >
                                            Abrir
                                        </Link>
                                    ),
                                },
                            ]}
                        />
                    </section>
                </>
            ) : (
                <>
                    {!closed && operations && closure && (
                        <ActionBanner
                            title={
                                closure.canComplete
                                    ? 'Pronta a concluir'
                                    : 'A conclusão aguarda verificações'
                            }
                            description={
                                closure.canComplete
                                    ? 'Os critérios de encerramento estão cumpridos.'
                                    : (closure.blockers[0] ??
                                      'Reveja os pedidos e a conferência financeira.')
                            }
                            action={
                                closure.canComplete
                                    ? {
                                          label: 'Rever encerramento',
                                          href: index.url({
                                              query: {
                                                  campaignId,
                                                  tab: 'encerramento',
                                              },
                                          }),
                                      }
                                    : closure.items.find(
                                            (item) => !item.complete,
                                        )
                                      ? {
                                            label: 'Rever pendência',
                                            href: closure.items.find(
                                                (item) => !item.complete,
                                            )!.href,
                                        }
                                      : undefined
                            }
                        />
                    )}
                    <SegmentedControl
                        label="Consulta da compra"
                        value={tab}
                        options={[
                            { value: 'pedidos', label: 'Pedidos' },
                            { value: 'movimentos', label: 'Movimentos' },
                            { value: 'encerramento', label: 'Encerramento' },
                        ]}
                        onChange={visitTab}
                    />
                    {tab === 'pedidos' && (
                        <section className="admin-panel">
                            <h2>Pedidos</h2>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    apply();
                                }}
                            >
                                <FilterBar>
                                    <input
                                        aria-label="Pesquisar pedidos"
                                        className="junta-input"
                                        placeholder="Participante ou referência"
                                        value={query}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                    />
                                    <button className="junta-btn junta-btn-md junta-btn-outline">
                                        Pesquisar
                                    </button>
                                </FilterBar>
                            </form>
                            <DataTable
                                records={props.orders.data}
                                rowKey={(row) => row.id}
                                label="Pedidos da campanha"
                                onRowClick={setOrder}
                                columns={[
                                    {
                                        key: 'participant',
                                        label: 'Participante',
                                        render: (row) => row.participantName,
                                    },
                                    {
                                        key: 'quantity',
                                        label: 'Quantidade',
                                        render: (row) => row.quantity,
                                    },
                                    {
                                        key: 'total',
                                        label: 'Total',
                                        render: (row) => money(row.total_cents),
                                    },
                                    {
                                        key: 'state',
                                        label: 'Estado',
                                        render: (row) => (
                                            <StatusPill
                                                state={row.state}
                                                compact
                                            />
                                        ),
                                    },
                                    {
                                        key: 'problem',
                                        label: 'Problema',
                                        render: (row) =>
                                            row.issues.some(
                                                (issue) =>
                                                    issue.status === 'open',
                                            )
                                                ? 'Ocorrência aberta'
                                                : row.status ===
                                                    'refund_pending'
                                                  ? 'Reembolso por confirmar'
                                                  : row.state.canVerify
                                                    ? 'Pagamento por verificar'
                                                    : '—',
                                    },
                                ]}
                            />
                            <PurchasePagination page={props.orders} />
                        </section>
                    )}

                    {tab === 'movimentos' && (
                        <>
                            <section className="admin-panel">
                                <div className="admin-section-heading">
                                    <h2>Movimentos</h2>
                                    {!closed &&
                                        view.campaign.purchasePhase ===
                                            'supply' && (
                                            <div className="admin-resource-actions">
                                                <button
                                                    className="junta-btn junta-btn-md junta-btn-outline"
                                                    onClick={() =>
                                                        setOperation({
                                                            kind: 'record',
                                                            campaignId:
                                                                campaignId!,
                                                            recordKind:
                                                                'carrier_due',
                                                        })
                                                    }
                                                >
                                                    Registar custo do
                                                    transportador
                                                </button>
                                                <button
                                                    className="junta-btn junta-btn-md junta-btn-primary"
                                                    onClick={() =>
                                                        setOperation({
                                                            kind: 'record',
                                                            campaignId:
                                                                campaignId!,
                                                            recordKind:
                                                                'supplier_settled',
                                                        })
                                                    }
                                                >
                                                    Registar liquidação
                                                </button>
                                            </div>
                                        )}
                                </div>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        apply();
                                    }}
                                >
                                    <FilterBar>
                                        <label>
                                            Desde
                                            <input
                                                className="junta-input"
                                                type="date"
                                                value={from}
                                                onChange={(event) =>
                                                    setFrom(event.target.value)
                                                }
                                            />
                                        </label>
                                        <label>
                                            Até
                                            <input
                                                className="junta-input"
                                                type="date"
                                                value={to}
                                                onChange={(event) =>
                                                    setTo(event.target.value)
                                                }
                                            />
                                        </label>
                                        <label>
                                            Tipo
                                            <select
                                                className="junta-input"
                                                value={type}
                                                onChange={(event) =>
                                                    setType(event.target.value)
                                                }
                                            >
                                                <option value="">Todos</option>
                                                {props.eventTypes.map(
                                                    (value) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {financeLabels[
                                                                value
                                                            ] ??
                                                                'Outro movimento'}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </label>
                                        <button className="junta-btn junta-btn-md junta-btn-outline">
                                            Aplicar
                                        </button>
                                        <a
                                            className="junta-btn junta-btn-md junta-btn-outline"
                                            href={exportMethod.url({
                                                query: {
                                                    ...props.filters,
                                                    campaignId,
                                                },
                                            })}
                                        >
                                            Exportar CSV
                                        </a>
                                    </FilterBar>
                                </form>
                                <MovementsTable page={props.events} />
                            </section>
                            <section className="admin-panel">
                                {props.campaignTotals && (
                                    <FinanceSummary
                                        totals={props.campaignTotals}
                                    />
                                )}{' '}
                                {filtered && (
                                    <FinanceSummary
                                        filtered
                                        totals={props.filteredTotals}
                                    />
                                )}
                            </section>
                        </>
                    )}
                    {tab === 'encerramento' && closure && (
                        <section className="admin-panel">
                            <h2>Verificação de encerramento</h2>
                            <ClosureChecklist closure={closure} />
                            {closed ? (
                                <p className="admin-muted">
                                    Esta compra está encerrada. Os dados ficam
                                    disponíveis para consulta.
                                </p>
                            ) : (
                                <>
                                    <button
                                        className="junta-btn junta-btn-md junta-btn-primary"
                                        disabled={!closure.canComplete}
                                        onClick={() =>
                                            setOperation({
                                                kind: 'complete',
                                                campaignId: campaignId!,
                                            })
                                        }
                                    >
                                        Concluir compra
                                    </button>
                                    {!closure.canComplete && (
                                        <ul className="admin-blockers">
                                            {closure.blockers.map((reason) => (
                                                <li key={reason}>{reason}</li>
                                            ))}
                                        </ul>
                                    )}
                                    {['payment', 'review'].includes(
                                        view.campaign.purchasePhase ?? '',
                                    ) && (
                                        <details className="admin-danger-zone">
                                            <summary>Cancelar compra</summary>
                                            <p>
                                                O cancelamento solicita o
                                                reembolso integral dos
                                                pagamentos confirmados.
                                            </p>
                                            <button
                                                className="junta-btn junta-btn-md junta-btn-outline"
                                                onClick={() =>
                                                    setOperation({
                                                        kind: 'cancel',
                                                        campaignId: campaignId!,
                                                        amountCents:
                                                            props.campaignTotals
                                                                ?.payment_received ??
                                                            0,
                                                    })
                                                }
                                            >
                                                Rever cancelamento
                                            </button>
                                        </details>
                                    )}
                                </>
                            )}
                        </section>
                    )}
                </>
            )}
            {order && (
                <OrderDrawer
                    key={order.id}
                    order={order}
                    onClose={() => setOrder(null)}
                    onOperation={setOperation}
                />
            )}
            {operation && (
                <FinancialConfirmDialog
                    operation={operation}
                    onSuccess={() => setOrder(null)}
                    onClose={() => setOperation(null)}
                />
            )}
        </Page>
    );
}
