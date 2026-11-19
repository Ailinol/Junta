import InputError from '@/components/input-error';
import { Link, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
    DataTable,
    ConfirmDialog,
    DeadlineLabel,
    TechnicalDetails,
} from '@/components/junta/OrganizerUi';
import { StatusPill } from '@/components/junta/StatusBadge';
import { ResourceDrawer } from '@/components/junta/ResourceDrawer';
import { PurchasePagination, money } from '@/components/junta/PurchaseForms';
import { DetailList } from '@/components/junta/Page';
import { maputoDate, relativeAge } from '@/lib/organizer-presentation';
import { record, resolve, cancel, complete } from '@/routes/admin/compras';
import { reconcile } from '@/routes/consumidor/pedidos';
import type {
    AdminCampaign,
    AdminOrder,
    Closure,
    Intervention,
    FinanceEvent,
} from '@/types/admin';
import type { PurchasePage } from '@/types/purchase';
export const financeLabels: Record<string, string> = {
    payment_received: 'Pagamento recebido',
    refund_paid: 'Reembolso confirmado',
    supplier_due: 'Devido ao fornecedor',
    carrier_due: 'Devido ao transportador',
    supplier_settled: 'Liquidado ao fornecedor',
    carrier_settled: 'Liquidado ao transportador',
    campaign_decided: 'Decisão da compra',
};
export function Age({ value }: { value: string }) {
    return (
        <time dateTime={value} title={maputoDate(value) + ' · Maputo'}>
            {relativeAge(value)}
        </time>
    );
}
export function InterventionQueue({
    page,
}: {
    page: PurchasePage<Intervention>;
}) {
    return (
        <>
            <DataTable
                records={page.data}
                rowKey={(row) => row.id}
                label="Intervenções"
                columns={[
                    {
                        key: 'campaign',
                        label: 'Campanha',
                        render: (row) => (
                            <span title={row.campaignTitle}>
                                {row.campaignTitle}
                            </span>
                        ),
                    },
                    {
                        key: 'participant',
                        label: 'Participante',
                        render: (row) => row.participantName ?? '—',
                    },
                    {
                        key: 'cause',
                        label: 'Causa',
                        render: (row) => (
                            <StatusPill
                                compact
                                state={{
                                    label: row.label,
                                    tone:
                                        row.kind === 'ready'
                                            ? 'success'
                                            : 'warning',
                                    icon: 'clock',
                                }}
                            />
                        ),
                    },
                    {
                        key: 'age',
                        label: 'Há quanto tempo',
                        render: (row) => <Age value={row.since} />,
                    },
                    {
                        key: 'action',
                        label: 'Acção',
                        render: (row) => (
                            <Link
                                className="junta-btn junta-btn-sm junta-btn-outline"
                                href={row.primaryAction.href}
                            >
                                {row.primaryAction.label}
                            </Link>
                        ),
                    },
                ]}
            />
            <PurchasePagination page={page} />
        </>
    );
}
export function CampaignFinanceHeader({ view }: { view: AdminCampaign }) {
    return (
        <div className="admin-finance-heading">
            <StatusPill state={view.workspace} compact />
            <DeadlineLabel
                deadline={view.workspace.deadline}
                compact
                blocked={view.workspace.deadline?.blocked}
            />
        </div>
    );
}
export function FinanceSummary({
    totals,
    filtered = false,
}: {
    totals: Record<string, number>;
    filtered?: boolean;
}) {
    const supplier =
        (totals.supplier_due ?? 0) - (totals.supplier_settled ?? 0);
    const carrier = (totals.carrier_due ?? 0) - (totals.carrier_settled ?? 0);
    return (
        <section className="admin-finance-summary">
            <h2>
                {filtered ? 'Total do filtro aplicado' : 'Total da campanha'}
            </h2>
            <dl>
                {[
                    ['Recebido', totals.payment_received],
                    ['Reembolsado', totals.refund_paid],
                    ['Líquido recebido', totals.net_received],
                    ['Devido ao fornecedor', totals.supplier_due],
                    ['Devido ao transportador', totals.carrier_due],
                    ['Liquidado ao fornecedor', totals.supplier_settled],
                    ['Liquidado ao transportador', totals.carrier_settled],
                    ['Saldo por liquidar', supplier + carrier],
                ].map(([label, value]) => (
                    <div key={String(label)}>
                        <dt>{label}</dt>
                        <dd>{money(Number(value ?? 0))}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
export function MovementsTable({ page }: { page: PurchasePage<FinanceEvent> }) {
    return (
        <>
            <DataTable
                records={page.data}
                rowKey={(row) => row.id}
                label="Movimentos financeiros"
                columns={[
                    {
                        key: 'date',
                        label: 'Data',
                        render: (row) => (
                            <time
                                title={maputoDate(row.created_at) + ' · Maputo'}
                            >
                                {maputoDate(row.created_at)}
                            </time>
                        ),
                    },
                    {
                        key: 'type',
                        label: 'Tipo',
                        render: (row) =>
                            financeLabels[row.kind] ?? 'Movimento financeiro',
                    },
                    {
                        key: 'reference',
                        label: 'Referência',
                        render: (row) => (
                            <span title={row.reference}>{row.reference}</span>
                        ),
                    },
                    {
                        key: 'amount',
                        label: 'Valor',
                        render: (row) => money(row.amount_cents),
                    },
                    {
                        key: 'reason',
                        label: 'Motivo',
                        render: (row) => (
                            <span title={row.reason}>{row.reason}</span>
                        ),
                    },
                ]}
            />
            <PurchasePagination page={page} />
        </>
    );
}
export function ClosureChecklist({ closure }: { closure: Closure }) {
    return (
        <ul className="admin-closure-list">
            {closure.items.map((item) => (
                <li key={item.key}>
                    <span
                        aria-label={
                            item.complete ? 'Verificado' : 'Por resolver'
                        }
                        className="admin-check"
                        data-complete={item.complete}
                    >
                        {item.complete ? '✓' : '○'}
                    </span>
                    <span>{item.label}</span>
                    {!item.complete && <Link href={item.href}>Resolver</Link>}
                </li>
            ))}
        </ul>
    );
}
type Operation = {
    kind: 'record' | 'resolve' | 'verify' | 'cancel' | 'complete';
    campaignId: number;
    order?: AdminOrder;
    issueId?: number;
    amountCents?: number;
    recordKind?: 'carrier_due' | 'supplier_settled';
};
export function FinancialConfirmDialog({
    operation,
    onClose,
    onSuccess,
}: {
    operation: Operation;
    onClose: () => void;
    onSuccess?: () => void;
}) {
    const form = useForm({
        kind: String(operation.recordKind ?? 'supplier_settled'),
        amount: '',
        reference: '',
        reason: '',
        resolutionType: 'resolved',
    });
    const generalErrors = Object.entries(form.errors).filter(
        ([key]) => !['amount', 'reference', 'reason'].includes(key),
    );
    const kind = operation.kind;
    const needsReason = ['record', 'resolve', 'cancel'].includes(kind);
    const needsReference = ['record', 'resolve'].includes(kind);
    const titles = {
        record:
            operation.recordKind === 'carrier_due'
                ? 'Registar custo do transportador'
                : 'Registar liquidação',
        resolve: 'Resolver ocorrência',
        verify: 'Verificar pagamento ou reembolso',
        cancel: 'Cancelar compra',
        complete: 'Concluir compra',
    };
    const descriptions = {
        record:
            form.data.kind === 'carrier_due'
                ? 'Regista um custo real do transportador. O valor passa a fazer parte do saldo por liquidar.'
                : 'Regista um pagamento já efectuado fora da plataforma. Não executa nenhuma transferência.',
        resolve:
            form.data.resolutionType === 'refund'
                ? 'Aprova o reembolso integral do pedido e inicia o processamento previsto no fluxo financeiro.'
                : 'Encerra a ocorrência com o motivo indicado, sem emitir um reembolso.',
        verify: 'Consulta o estado no prestador e processa reembolsos pendentes. Um estado confirmado pode actualizar os movimentos financeiros. Não inicia uma nova cobrança.',
        cancel: 'Cancela a compra: os valores cobrados serão devolvidos integralmente. Os reembolsos podem ficar pendentes no prestador.',
        complete:
            'Encerra a compra após verificar entregas, ocorrências, reembolsos e saldos. Os registos financeiros ficam disponíveis para consulta.',
    };
    const [clientError, setClientError] = useState('');
    return (
        <ConfirmDialog
            className="admin-dialog"
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
            title={titles[kind]}
            description={descriptions[kind]}
            processing={form.processing}
            danger={kind === 'cancel'}
            confirmLabel={
                kind === 'cancel'
                    ? 'Confirmar cancelamento'
                    : kind === 'complete'
                      ? 'Confirmar conclusão'
                      : kind === 'verify'
                        ? 'Consultar prestador'
                        : 'Confirmar registo'
            }
            onConfirm={() => {
                if (
                    (needsReason && !form.data.reason.trim()) ||
                    (needsReference && !form.data.reference.trim()) ||
                    (kind === 'record' && Number(form.data.amount) <= 0)
                ) {
                    setClientError(
                        'Preencha o valor positivo, quando aplicável, a referência e o motivo obrigatórios.',
                    );
                    return;
                }
                setClientError('');
                const route =
                    kind === 'record'
                        ? record(operation.campaignId)
                        : kind === 'resolve'
                          ? resolve(operation.issueId!)
                          : kind === 'verify'
                            ? reconcile(operation.order!.id)
                            : kind === 'cancel'
                              ? cancel(operation.campaignId)
                              : complete(operation.campaignId);
                form.post(route.url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Operação registada.');
                        onSuccess?.();
                        onClose();
                    },
                });
            }}
        >
            {operation.amountCents !== undefined && (
                <p>
                    Valor cobrado na campanha:{' '}
                    <strong>{money(operation.amountCents)}</strong>. A devolução
                    segue o estado de cada pedido.
                </p>
            )}
            {operation.order && (
                <p>
                    <strong>
                        Valor integral do pedido:{' '}
                        {money(operation.order.total_cents)}
                    </strong>
                </p>
            )}
            {kind === 'record' && (
                <>
                    <label>
                        Tipo de registo
                        <select
                            className="junta-input"
                            value={form.data.kind}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('kind', event.target.value)
                            }
                        >
                            {(operation.recordKind === 'carrier_due'
                                ? ['carrier_due']
                                : ['supplier_settled', 'carrier_settled']
                            ).map((value) => (
                                <option key={value} value={value}>
                                    {financeLabels[value]}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Valor em MT
                        <input
                            className="junta-input"
                            name="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            disabled={form.processing}
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                        />
                        <InputError message={form.errors.amount} />
                    </label>
                    <p>
                        Valor a registar:{' '}
                        <strong>
                            {money(
                                Math.round(Number(form.data.amount || 0) * 100),
                            )}
                        </strong>
                    </p>
                </>
            )}
            {kind === 'resolve' && (
                <label>
                    Decisão
                    <select
                        className="junta-input"
                        value={form.data.resolutionType}
                        disabled={form.processing}
                        onChange={(event) =>
                            form.setData('resolutionType', event.target.value)
                        }
                    >
                        <option value="resolved">
                            Resolvida, sem reembolso
                        </option>
                        <option value="refund">
                            Aprovar reembolso integral
                        </option>
                    </select>
                </label>
            )}
            {needsReference && (
                <label>
                    Referência do comprovativo
                    <input
                        name="reference"
                        className="junta-input"
                        required
                        maxLength={200}
                        disabled={form.processing}
                        value={form.data.reference}
                        onChange={(event) =>
                            form.setData('reference', event.target.value)
                        }
                    />
                    <InputError message={form.errors.reference} />
                </label>
            )}
            {needsReason && (
                <label>
                    Motivo obrigatório
                    <textarea
                        className="junta-input"
                        name="reason"
                        maxLength={2000}
                        required
                        disabled={form.processing}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
                    <InputError message={form.errors.reason} />
                </label>
            )}
            {(clientError || generalErrors.length > 0) && (
                <div role="alert" className="text-destructive">
                    {clientError}
                    {generalErrors.map(([key, message]) => (
                        <p key={key}>{message}</p>
                    ))}
                </div>
            )}
        </ConfirmDialog>
    );
}
export function OrderDrawer({
    order,
    onClose,
    onOperation,
}: {
    order: AdminOrder;
    onClose: () => void;
    onOperation: (operation: Operation) => void;
}) {
    return (
        <ResourceDrawer
            title={order.participantName}
            description="Pedido e acompanhamento financeiro"
            onClose={onClose}
        >
            <StatusPill state={order.state} compact />
            <DetailList
                items={[
                    { label: 'Quantidade', value: order.quantity },
                    {
                        label: 'Mercadoria',
                        value: money(order.merchandise_cents),
                    },
                    { label: 'Comissão', value: money(order.commission_cents) },
                    {
                        label: 'Transporte do fornecedor',
                        value: money(order.supplier_transport_cents),
                    },
                    {
                        label: 'Entrega',
                        value: money(order.delivery_fee_cents),
                    },
                    {
                        label: 'Total do pedido',
                        value: money(order.total_cents),
                    },
                ]}
            />
            {order.refunds.length > 0 && (
                <section>
                    <h3>Reembolsos</h3>
                    {order.refunds.map((refund, index) => (
                        <p key={index}>
                            {money(refund.amount_cents)} ·{' '}
                            {refund.status === 'confirmed'
                                ? 'Confirmado'
                                : 'Por confirmar'}
                        </p>
                    ))}
                </section>
            )}
            {order.issues.map((issue) => (
                <section key={issue.id}>
                    <h3>
                        {issue.status === 'open'
                            ? 'Ocorrência aberta'
                            : 'Ocorrência resolvida'}
                    </h3>
                    <p>{issue.description}</p>
                    {issue.resolution && <p>{issue.resolution}</p>}
                    {!order.state.isFinal &&
                        order.state.canResolve &&
                        issue.status === 'open' && (
                            <button
                                className="junta-btn junta-btn-md junta-btn-outline"
                                onClick={() =>
                                    onOperation({
                                        kind: 'resolve',
                                        campaignId: order.campaign_id,
                                        order,
                                        issueId: issue.id,
                                    })
                                }
                            >
                                Resolver ocorrência
                            </button>
                        )}
                </section>
            ))}
            {!order.state.isFinal && order.state.canVerify && (
                <button
                    className="junta-btn junta-btn-md junta-btn-outline"
                    onClick={() =>
                        onOperation({
                            kind: 'verify',
                            campaignId: order.campaign_id,
                            order,
                        })
                    }
                >
                    Verificar no prestador
                </button>
            )}
            <TechnicalDetails>
                <p>Pedido: {order.reference}</p>
                {order.attempts.map((attempt) => (
                    <p key={attempt.id}>
                        Tentativa: {attempt.reference} · {attempt.status}
                    </p>
                ))}
            </TechnicalDetails>
        </ResourceDrawer>
    );
}
export function ReportBlock({
    title,
    description,
    children,
    icon,
    actions,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    icon?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <section className="admin-panel admin-report-block">
            <div className="report-block-heading">
                <div>
                    {icon && <span className="report-block-icon">{icon}</span>}
                    <h2>{title}</h2>
                </div>
                {actions}
            </div>
            {description && <p className="admin-muted">{description}</p>}
            {children}
        </section>
    );
}
