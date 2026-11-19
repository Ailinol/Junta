import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Page } from '@/components/junta/Page';
import {
    OrderStatus,
    OrderTimeline,
    CodeDisplay,
    ConsumerBreadcrumbs,
    QuantityStepper,
} from '@/components/junta/ConsumerUi';
import { DeadlineLabel, ConfirmDialog } from '@/components/junta/OrganizerUi';
import {
    OrderBreakdown,
    PaymentTechnicalDetails,
    TestPaymentNotice,
} from '@/components/junta/PurchaseCheckout';
import { EmptyState } from '@/components/junta/EmptyState';
import { PurchaseForm, money } from '@/components/junta/PurchaseForms';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { checkout, issue } from '@/routes/consumidor/pedidos';
import { index } from '@/routes/consumidor/campanhas';
import { update, destroy } from '@/routes/consumidor/participacoes';
import { explorar } from '@/routes/consumidor';
import type { Purchase } from '@/types/purchase';
export default function Tracking({ purchase }: { purchase?: Purchase | null }) {
    const { data } = usePrototypeStore();
    const [dialog, setDialog] = useState<'edit' | 'cancel' | null>(null);
    const form = useForm({ quantity: purchase?.participation?.quantity ?? 1 });
    if (!purchase?.view)
        return (
            <Page title="A minha compra" className="consumer-page">
                <EmptyState
                    title="Compra não encontrada"
                    actionHref={index.url()}
                    actionLabel="As minhas compras"
                />
            </Page>
        );
    const state = purchase.view;
    const order = purchase.order;
    const campaign = data.campaigns.find((c) => c.id === purchase.id);
    const config = order?.conditions ?? purchase.config;
    const refund = order?.refunds.filter((r) => r.status === 'refunded');
    const refundedAt = refund?.[0]?.updated_at;
    return (
        <Page title={purchase.title} className="consumer-page">
            <ConsumerBreadcrumbs
                items={[
                    { label: 'As minhas compras', href: index.url() },
                    { label: 'Compra' },
                ]}
            />
            <div className="consumer-tracking-status">
                <OrderStatus state={state} />
            </div>
            <TestPaymentNotice enabled={purchase.testTools} />
            {!state.isFinal && state.primaryAction && (
                <section className="consumer-action-strip">
                    <div>
                        {state.primaryAction.code === 'pay' ? (
                            <>
                                <strong>
                                    A sua compra está pronta para pagar
                                </strong>
                                {state.deadline && (
                                    <DeadlineLabel
                                        deadline={state.deadline}
                                        compact
                                    />
                                )}
                            </>
                        ) : (
                            <strong>
                                O seu pedido está pronto. Apresente o código ao
                                receber.
                            </strong>
                        )}
                    </div>
                    {state.primaryAction.code === 'pay' && order ? (
                        <Link
                            href={checkout(order.id)}
                            className="junta-btn junta-btn-md junta-btn-primary"
                        >
                            Pagar
                        </Link>
                    ) : (
                        <a
                            href="#collection-code"
                            className="junta-btn junta-btn-md junta-btn-primary"
                        >
                            Ver código
                        </a>
                    )}
                </section>
            )}
            {state.code === 'refunded' && order ? (
                <section className="consumer-refund-card">
                    <h2>Reembolsado</h2>
                    <strong>{money(order.total_cents)}</strong>
                    <p>
                        Valor integral devolvido
                        {refundedAt
                            ? ` em ${new Date(refundedAt).toLocaleDateString('pt-PT', { timeZone: 'Africa/Maputo' })}`
                            : '.'}
                    </p>
                </section>
            ) : (
                <OrderTimeline state={state} />
            )}
            {!state.primaryAction && state.code !== 'refunded' && (
                <p className="consumer-muted">{state.description}</p>
            )}
            <div className="consumer-tracking-grid">
                <section className="consumer-panel">
                    <h2>Resumo da compra</h2>
                    <p>
                        {order?.quantity ?? purchase.participation?.quantity}{' '}
                        {campaign?.unit ?? 'unidades'}
                    </p>
                    {order ? (
                        <>
                            <p className="consumer-help">
                                {order.delivery_method === 'entrega'
                                    ? 'Entrega'
                                    : 'Recolha'}
                            </p>
                            <OrderBreakdown amounts={order} />
                        </>
                    ) : (
                        <>
                            <p className="consumer-help">
                                Preço por confirmar. Ainda não existe cobrança.
                            </p>
                            {state.deadline && (
                                <DeadlineLabel
                                    deadline={state.deadline}
                                    compact
                                />
                            )}
                            {purchase.participation?.notes && (
                                <p>{purchase.participation.notes}</p>
                            )}
                        </>
                    )}
                    {state.canEditInterest && (
                        <div className="consumer-interest-actions">
                            <button
                                type="button"
                                className="junta-btn junta-btn-sm junta-btn-outline"
                                onClick={() => setDialog('edit')}
                            >
                                Alterar interesse
                            </button>
                            <button
                                type="button"
                                className="consumer-text-link"
                                onClick={() => setDialog('cancel')}
                            >
                                Cancelar interesse
                            </button>
                        </div>
                    )}
                </section>
                <section className="consumer-panel">
                    <h2>
                        {order?.delivery_method === 'entrega'
                            ? 'Entrega'
                            : 'Levantamento'}
                    </h2>
                    <p>
                        {order?.delivery_method === 'entrega'
                            ? order.delivery_address
                            : (config?.pickup_point ?? campaign?.location)}
                    </p>
                    {state.code === 'ready' && order?.collectionCode && (
                        <CodeDisplay code={order.collectionCode} />
                    )}
                    {!order && (
                        <p className="consumer-help">
                            O ponto de recolha será confirmado quando abrirem os
                            pagamentos.
                        </p>
                    )}
                </section>
            </div>
            {state.canReportIssue && order && (
                <details className="consumer-help-disclosure">
                    <summary>Tenho um problema com esta compra</summary>
                    <PurchaseForm
                        action={issue(order.id)}
                        label="Enviar problema"
                        fields={[
                            {
                                name: 'type',
                                label: 'O que aconteceu?',
                                options: [
                                    {
                                        value: 'not_received',
                                        label: 'Não recebi',
                                    },
                                    { value: 'damaged', label: 'Danificado' },
                                    { value: 'missing', label: 'Em falta' },
                                ],
                            },
                            { name: 'description', label: 'Descrição' },
                        ]}
                    />
                </details>
            )}
            {order?.issues.length ? (
                <section className="consumer-panel">
                    <h2>O seu problema</h2>
                    {order.issues.map((entry) => (
                        <div key={entry.id}>
                            <p>{entry.description}</p>
                            {entry.resolution && <p>{entry.resolution}</p>}
                        </div>
                    ))}
                </section>
            ) : null}
            <PaymentTechnicalDetails purchase={purchase} />
            <ConfirmDialog
                open={dialog !== null}
                onOpenChange={(open) => {
                    if (!open) setDialog(null);
                }}
                title={
                    dialog === 'cancel'
                        ? 'Cancelar o interesse?'
                        : 'Alterar o interesse?'
                }
                description={
                    dialog === 'cancel'
                        ? 'A quantidade deixa de fazer parte da compra em grupo.'
                        : 'Confirme a nova quantidade de interesse.'
                }
                confirmLabel={
                    dialog === 'cancel'
                        ? 'Cancelar interesse'
                        : 'Guardar quantidade'
                }
                processing={form.processing}
                onConfirm={() => {
                    if (!purchase.participation) return;
                    const options = {
                        preserveScroll: true,
                        onSuccess: () => setDialog(null),
                    };
                    if (dialog === 'cancel')
                        form.delete(
                            destroy.url(purchase.participation.id),
                            options,
                        );
                    else
                        form.patch(
                            update.url(purchase.participation.id),
                            options,
                        );
                }}
            >
                {dialog === 'edit' && (
                    <QuantityStepper
                        value={form.data.quantity}
                        onChange={(value) => form.setData('quantity', value)}
                        unit={campaign?.unit ?? 'unidades'}
                        disabled={form.processing}
                    />
                )}
                {form.errors.quantity && (
                    <p role="alert" className="consumer-error">
                        {form.errors.quantity}
                    </p>
                )}
            </ConfirmDialog>
            {state.isFinal && !order && (
                <Link href={explorar()}>Explorar campanhas</Link>
            )}
        </Page>
    );
}
