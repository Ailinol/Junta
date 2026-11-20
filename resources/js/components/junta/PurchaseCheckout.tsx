import { useEffect, useId, useState } from 'react';
import { Link, useForm, usePoll } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    LoaderCircle,
    Smartphone,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import {
    DeadlineLabel,
    TechnicalDetails,
} from '@/components/junta/OrganizerUi';
import {
    PaymentMethodPicker,
    PhoneField,
    SegmentedControl,
} from '@/components/junta/ConsumerUi';
import { PurchaseForm, money } from '@/components/junta/PurchaseForms';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { campaignUnit } from '@/lib/campaign-presentation';
import { localPhone, paymentFormReady } from '@/lib/consumer-presentation';
import {
    checkout,
    pay,
    reconcile,
    simulate,
} from '@/routes/consumidor/pedidos';
import { show as tracking } from '@/routes/consumidor/acompanhamento';
import { explorar } from '@/routes/consumidor';
import type { Campaign } from '@/types/junta';
import type { Purchase, OrderAmounts } from '@/types/purchase';

export function TestPaymentNotice({ enabled }: { enabled: boolean }) {
    return enabled ? (
        <p className="consumer-test-notice">
            Pagamento de teste. Não movimenta dinheiro real.
        </p>
    ) : null;
}
export function OrderBreakdown({ amounts }: { amounts: OrderAmounts }) {
    return (
        <dl className="consumer-price-breakdown">
            {[
                ['Mercadoria', amounts.merchandise_cents],
                ['Comissão', amounts.commission_cents],
                ['Transporte', amounts.supplier_transport_cents],
                ['Entrega', amounts.delivery_fee_cents],
                ['Total', amounts.total_cents],
            ].map(([label, cents]) => (
                <div key={label}>
                    <dt>{label}</dt>
                    <dd>{money(Number(cents))}</dd>
                </div>
            ))}
        </dl>
    );
}
export function OrderSummary({
    purchase,
    campaign,
    amounts,
}: {
    purchase: Purchase;
    campaign: Campaign;
    amounts: OrderAmounts;
}) {
    const quantity =
        purchase.order?.quantity ?? purchase.participation?.quantity ?? 1;
    const contents = (
        <>
            <div className="consumer-summary-product">
                <CampaignVisual
                    campaign={campaign}
                    categoryFallback
                    thumbnail
                />
                <div>
                    <h3>{campaign.title}</h3>
                    <p>
                        {quantity} {campaignUnit(campaign.unit, quantity)}
                    </p>
                </div>
            </div>
            <OrderBreakdown amounts={amounts} />
            {purchase.view?.deadline && (
                <DeadlineLabel deadline={purchase.view.deadline} compact />
            )}
            <p className="consumer-help">
                Mínimo de {purchase.config?.minimum_quantity} {campaign.unit}.
                Se não for atingido, devolvemos o valor total.
            </p>
        </>
    );
    return (
        <>
            <aside className="consumer-summary-desktop consumer-panel">
                <h2>Resumo da compra</h2>
                {contents}
            </aside>
            <details className="consumer-summary-mobile consumer-panel">
                <summary>
                    {quantity} {campaignUnit(campaign.unit, quantity)} ·{' '}
                    {money(amounts.total_cents)}
                    <span aria-hidden="true">⌄</span>
                </summary>
                <div>{contents}</div>
            </details>
        </>
    );
}
export function PaymentTechnicalDetails({ purchase }: { purchase: Purchase }) {
    const order = purchase.order;
    return order ? (
        <TechnicalDetails>
            <p>Pedido: {order.reference}</p>
            <p>Condições: {order.terms_version}</p>
            {order.attempts.map((attempt) => (
                <p key={attempt.id}>Pagamento: {attempt.reference}</p>
            ))}
        </TechnicalDetails>
    ) : null;
}
export function PaymentTestTools({ purchase }: { purchase: Purchase }) {
    return purchase.testTools &&
        purchase.order &&
        purchase.view?.code === 'payment_pending' ? (
        <details className="consumer-test-tools">
            <summary>Ferramentas de teste</summary>
            <PurchaseForm
                action={simulate(purchase.order.id)}
                label="Simular resultado"
                fields={[
                    {
                        name: 'status',
                        label: 'Resultado',
                        options: [
                            { value: 'paid', label: 'Pagamento aprovado' },
                            { value: 'failed', label: 'Pagamento recusado' },
                        ],
                    },
                ]}
            />
        </details>
    ) : null;
}
export function WaitingScreen({ purchase }: { purchase: Purchase }) {
    const order = purchase.order;
    const confirmation = purchase.confirmation;
    const [offset] = useState(
        () => new Date(purchase.serverNow).getTime() - Date.now(),
    );
    const [now, setNow] = useState(() =>
        new Date(purchase.serverNow).getTime(),
    );
    usePoll(3000, { only: ['purchase'] });
    useEffect(() => {
        const timer = window.setInterval(
            () => setNow(Date.now() + offset),
            1000,
        );
        return () => window.clearInterval(timer);
    }, [offset]);
    const seconds = Math.max(
        0,
        Math.ceil(
            (new Date(confirmation?.expiresAt ?? purchase.serverNow).getTime() -
                now) /
                1000,
        ),
    );
    const waiting = seconds === 0;
    return (
        <section className="consumer-payment-state" aria-live="polite">
            <div className="consumer-waiting-icon">
                <Smartphone className="size-9" />
                <LoaderCircle className="size-5 animate-spin" />
            </div>
            <h1>
                {waiting
                    ? 'Ainda a aguardar confirmação'
                    : confirmation?.phone
                      ? 'Confirme no seu telemóvel'
                      : 'A confirmar o pagamento'}
            </h1>
            <p>
                {confirmation?.phone
                    ? `Enviámos um pedido de pagamento ${confirmation.method} para ${confirmation.phone}. Introduza o seu PIN para concluir.`
                    : 'Estamos a confirmar o resultado do seu pagamento.'}
            </p>
            {!waiting && (
                <div className="consumer-countdown">
                    <span role="timer" aria-label="Tempo restante">
                        {String(Math.floor(seconds / 60)).padStart(2, '0')}:
                        {String(seconds % 60).padStart(2, '0')}
                    </span>
                    <progress
                        value={seconds}
                        max={180}
                        aria-label="Tempo de confirmação restante"
                    />
                </div>
            )}
            <p className="consumer-help">
                {waiting
                    ? 'A confirmação está a demorar. Continuamos a verificar. Não será cobrado duas vezes; não inicie outro pagamento.'
                    : 'O estado actualiza automaticamente. Pode aguardar nesta página.'}
            </p>
            {order && (
                <PurchaseForm
                    action={reconcile(order.id)}
                    label="Já confirmei — verificar agora"
                    secondary
                />
            )}
        </section>
    );
}
export function ResultScreen({
    purchase,
    success,
}: {
    purchase: Purchase;
    success: boolean;
}) {
    const order = purchase.order;
    const retry =
        !!order &&
        !purchase.view?.isFinal &&
        purchase.view?.primaryAction?.code === 'pay';
    return (
        <section className="consumer-payment-state" aria-live="polite">
            <span
                className="consumer-result-icon"
                data-success={success || undefined}
            >
                {success ? (
                    <CheckCircle2 className="size-12" />
                ) : (
                    <CircleAlert className="size-12" />
                )}
            </span>
            <h1>
                {success
                    ? 'Pagamento recebido'
                    : purchase.view?.isFinal
                      ? purchase.view.label
                      : 'Não foi possível concluir o pagamento'}
            </h1>
            <p>{purchase.title}</p>
            {order && (
                <p className="consumer-result-total">
                    {order.quantity} unidades · {money(order.total_cents)}
                </p>
            )}
            <p className="consumer-muted">
                {success
                    ? 'Vamos avisá-lo quando a mercadoria estiver pronta.'
                    : purchase.confirmation?.canRetry
                      ? 'O pagamento foi recusado. Confirme os dados e tente novamente.'
                      : purchase.view?.description}
            </p>
            <div className="consumer-result-actions">
                {success ? (
                    <Link
                        className="junta-btn junta-btn-md junta-btn-primary"
                        href={tracking(purchase.id)}
                    >
                        Ver a minha compra
                    </Link>
                ) : retry && order ? (
                    <Link
                        className="junta-btn junta-btn-md junta-btn-primary"
                        href={checkout(order.id, { query: { retry: 1 } })}
                    >
                        Tentar novamente
                    </Link>
                ) : null}
                {!success && retry && order ? (
                    <Link href={checkout(order.id, { query: { retry: 1 } })}>
                        Escolher outro método
                    </Link>
                ) : (
                    <Link
                        className="junta-btn junta-btn-md junta-btn-outline"
                        href={explorar()}
                    >
                        Explorar campanhas
                    </Link>
                )}
            </div>
        </section>
    );
}
export function PurchaseCheckout({
    purchase,
    campaign,
}: {
    purchase: Purchase;
    campaign: Campaign;
}) {
    const order = purchase.order;
    const config = order?.conditions ?? purchase.config;
    const id = useId();
    const methods = purchase.paymentMethods ?? [];
    const form = useForm({
        deliveryMethod: order?.accepted_at
            ? order.delivery_method
            : (purchase.preferredDelivery ??
              order?.delivery_method ??
              'recolha'),
        deliveryAddress:
            order?.delivery_address ?? purchase.deliveryAddress ?? '',
        paymentMethod: methods.length === 1 ? methods[0].code : '',
        paymentPhone: purchase.paymentContact
            ? '+258' + localPhone(purchase.paymentContact)
            : '',
        termsVersion: config?.terms_version ?? '',
        acceptTerms: false,
    });
    if (!order || !config || !purchase.view?.primaryAction) return null;
    const method = methods.find(
        (entry) => entry.code === form.data.paymentMethod,
    );
    const amounts = order.accepted_at
        ? order
        : ((form.data.deliveryMethod === 'entrega'
              ? purchase.deliveryQuote
              : purchase.quote) ?? order);
    const ready = paymentFormReady(form.data, methods) && !form.processing;
    const error = (field: keyof typeof form.data) => (
        <InputError
            role="alert"
            id={`${id}-${field}-error`}
            message={form.errors[field]}
        />
    );
    return (
        <div className="consumer-checkout-grid">
            <form
                id="consumer-checkout"
                className="consumer-checkout-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (ready)
                        form.post(pay.url(order.id), {
                            preserveScroll: true,
                            onSuccess: () => form.reset('paymentPhone'),
                        });
                }}
            >
                <section className="consumer-panel">
                    <h2>
                        <span>1</span> Entrega
                    </h2>
                    <SegmentedControl
                        label="Modalidade de entrega"
                        value={form.data.deliveryMethod}
                        options={[
                            { value: 'recolha', label: 'Recolha' },
                            ...(purchase.deliveryQuote
                                ? [{ value: 'entrega', label: 'Entrega' }]
                                : []),
                        ]}
                        disabled={!!order.accepted_at || form.processing}
                        onChange={(value) =>
                            form.setData('deliveryMethod', value)
                        }
                    />
                    {error('deliveryMethod')}
                    {form.data.deliveryMethod === 'entrega' ? (
                        <div className="consumer-field mt-4">
                            <label htmlFor={`${id}-address`}>
                                Morada de entrega
                            </label>
                            <textarea
                                id={`${id}-address`}
                                className="junta-input"
                                autoComplete="street-address"
                                value={form.data.deliveryAddress}
                                onChange={(event) =>
                                    form.setData(
                                        'deliveryAddress',
                                        event.target.value,
                                    )
                                }
                                required
                                disabled={
                                    !!order.accepted_at || form.processing
                                }
                                maxLength={1000}
                                aria-invalid={!!form.errors.deliveryAddress}
                                aria-describedby={`${id}-deliveryAddress-error`}
                            />
                            {error('deliveryAddress')}
                            <p className="consumer-help">
                                {config.delivery_zone} · Tarifa:{' '}
                                {money(config.delivery_fee_cents)}
                            </p>
                        </div>
                    ) : (
                        <p className="consumer-pickup-point">
                            {config.pickup_point}
                        </p>
                    )}
                    {config.pricing_policy && (
                        <p className="consumer-help">
                            {config.delivery_provider === 'supplier'
                                ? 'O fornecedor inclui o transporte até ao ponto. Sem quota adicional de entrega.'
                                : `A sua quota de transporte até ao ponto é ${money(order.delivery_fee_cents)}, repartida pela quantidade de cada participante e recebida pela plataforma.`}
                        </p>
                    )}
                </section>
                <section className="consumer-panel">
                    <h2>
                        <span>2</span> Método de pagamento
                    </h2>
                    <PaymentMethodPicker
                        methods={methods}
                        value={form.data.paymentMethod}
                        onChange={(value) =>
                            form.setData('paymentMethod', value)
                        }
                        disabled={form.processing}
                    />
                    {error('paymentMethod')}
                    {methods.length === 0 && (
                        <p>
                            Os pagamentos estão temporariamente indisponíveis.
                        </p>
                    )}
                </section>
                <section className="consumer-panel">
                    <h2>
                        <span>3</span> Dados do pagamento
                    </h2>
                    {method?.requiresPhone ? (
                        <PhoneField
                            value={form.data.paymentPhone}
                            onChange={(value) =>
                                form.setData('paymentPhone', value)
                            }
                            error={form.errors.paymentPhone}
                            disabled={form.processing}
                        />
                    ) : (
                        <p className="consumer-help">
                            Escolha um método para indicar os dados de
                            pagamento.
                        </p>
                    )}
                </section>
                <section className="consumer-panel">
                    <h2>
                        <span>4</span> Termos da compra
                    </h2>
                    <label className="consumer-terms">
                        <input
                            type="checkbox"
                            checked={form.data.acceptTerms}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData(
                                    'acceptTerms',
                                    event.target.checked,
                                )
                            }
                            aria-invalid={!!form.errors.acceptTerms}
                            aria-describedby={`${id}-acceptTerms-error`}
                        />
                        Li e aceito os termos da compra
                    </label>
                    <Dialog>
                        <DialogTrigger asChild>
                            <button
                                type="button"
                                className="consumer-text-link"
                            >
                                Ler os termos da compra
                            </button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Termos da compra</DialogTitle>
                                <DialogDescription>
                                    Condições aplicáveis à sua compra.
                                </DialogDescription>
                            </DialogHeader>
                            <p className="max-h-80 overflow-y-auto text-sm leading-7 whitespace-pre-wrap">
                                {config.terms}
                            </p>
                        </DialogContent>
                    </Dialog>
                    {error('acceptTerms')}
                    {error('termsVersion')}
                </section>
                {Object.entries(form.errors)
                    .filter(([key]) => !Object.hasOwn(form.data, key))
                    .map(([key, message]) => (
                        <p role="alert" className="consumer-error" key={key}>
                            {message}
                        </p>
                    ))}
                <div className="consumer-checkout-submit">
                    <button
                        type="submit"
                        className="junta-btn junta-btn-lg junta-btn-primary"
                        disabled={!ready}
                    >
                        {form.processing
                            ? 'A enviar…'
                            : `Pagar ${money(amounts.total_cents)}`}
                    </button>
                </div>
            </form>
            <OrderSummary
                purchase={purchase}
                campaign={campaign}
                amounts={amounts}
            />
        </div>
    );
}
