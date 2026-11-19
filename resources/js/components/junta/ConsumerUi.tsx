import { Link } from '@inertiajs/react';
import { Check, Copy, Minus, Plus, Smartphone } from 'lucide-react';
import { useId, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { StatusPill } from '@/components/junta/StatusBadge';
import { DeadlineLabel } from '@/components/junta/OrganizerUi';
import { money } from '@/components/junta/PurchaseForms';
import { campaignUnit } from '@/lib/campaign-presentation';
import { localPhone, orderDestination } from '@/lib/consumer-presentation';
import { show as tracking } from '@/routes/consumidor/acompanhamento';
import { checkout } from '@/routes/consumidor/pedidos';
import type {
    ConsumerOrder,
    ConsumerState,
    PaymentMethod,
} from '@/types/consumer';

export function QuantityStepper({
    value,
    onChange,
    unit,
    disabled = false,
}: {
    value: number;
    onChange: (value: number) => void;
    unit: string;
    disabled?: boolean;
}) {
    const id = useId();
    return (
        <div className="consumer-quantity">
            <label htmlFor={id}>
                Quantidade <span>({unit})</span>
            </label>
            <div>
                <button
                    type="button"
                    aria-label="Diminuir quantidade"
                    disabled={disabled || value <= 1}
                    onClick={() => onChange(value - 1)}
                >
                    <Minus className="size-4" />
                </button>
                <input
                    id={id}
                    type="number"
                    min={1}
                    max={2147483647}
                    step={1}
                    inputMode="numeric"
                    value={value || ''}
                    disabled={disabled}
                    onChange={(event) => onChange(Number(event.target.value))}
                />
                <button
                    type="button"
                    aria-label="Aumentar quantidade"
                    disabled={disabled || value >= 2147483647}
                    onClick={() => onChange(value + 1)}
                >
                    <Plus className="size-4" />
                </button>
            </div>
        </div>
    );
}
export function SegmentedControl({
    label,
    value,
    options,
    onChange,
    disabled = false,
}: {
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    return (
        <div className="consumer-segmented" role="group" aria-label={label}>
            {options.map((option) => (
                <button
                    type="button"
                    key={option.value}
                    aria-pressed={option.value === value}
                    disabled={disabled}
                    onClick={() => onChange(option.value)}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
export function PaymentMethodPicker({
    methods,
    value,
    onChange,
    disabled,
}: {
    methods: PaymentMethod[];
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    const id = useId();
    return (
        <div className="consumer-payment-methods">
            {methods.map((method) => (
                <label
                    key={method.code}
                    data-selected={method.code === value || undefined}
                >
                    <input
                        type="radio"
                        name={id}
                        value={method.code}
                        checked={value === method.code}
                        onChange={() => onChange(method.code)}
                        disabled={disabled}
                    />
                    <span className="consumer-method-icon" aria-hidden="true">
                        <Smartphone className="size-5" />
                    </span>
                    <span>
                        <strong>{method.label}</strong>
                        <small>Pagar com o seu número {method.label}</small>
                    </span>
                    {value === method.code && (
                        <Check
                            className="ml-auto size-5 text-primary"
                            aria-hidden="true"
                        />
                    )}
                </label>
            ))}
        </div>
    );
}
export function PhoneField({
    value,
    onChange,
    error,
    disabled,
    helper = true,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
    disabled?: boolean;
    helper?: boolean;
}) {
    const id = useId();
    const digits = localPhone(value);
    const masked = [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 9)]
        .filter(Boolean)
        .join(' ');
    return (
        <div className="consumer-field">
            <label htmlFor={id}>Número de telefone</label>
            <div className="consumer-phone">
                <span aria-hidden="true">+258</span>
                <input
                    id={id}
                    type="tel"
                    inputMode="numeric"
                    autoComplete="tel-national"
                    aria-label="Número de telefone, prefixo +258"
                    placeholder="84 123 4567"
                    value={masked}
                    onChange={(event) =>
                        onChange('+258' + localPhone(event.target.value))
                    }
                    disabled={disabled}
                    aria-invalid={!!error}
                    aria-describedby={id + '-help'}
                />
            </div>
            <p
                id={id + '-help'}
                className={error ? 'consumer-error' : 'consumer-help'}
                role={error ? 'alert' : undefined}
            >
                {error ??
                    (helper
                        ? 'Vai receber um pedido de confirmação neste número.'
                        : 'Contacto de pagamento predefinido.')}
            </p>
        </div>
    );
}
export function OrderStatus({ state }: { state: ConsumerState }) {
    return (
        <span className="consumer-state" data-tone={state.tone}>
            <StatusPill compact state={{ ...state, icon: 'clock' }} />
        </span>
    );
}
export function OrderTimeline({ state }: { state: ConsumerState }) {
    return state.steps.length ? (
        <ol className="consumer-timeline" aria-label="Progresso da compra">
            {state.steps.map((step, index) => (
                <li
                    key={step.label}
                    data-status={step.status}
                    aria-current={
                        step.status === 'current' ? 'step' : undefined
                    }
                >
                    <span>
                        {step.status === 'complete' ? (
                            <Check className="size-4" aria-hidden="true" />
                        ) : (
                            index + 1
                        )}
                    </span>
                    <p>{step.label}</p>
                </li>
            ))}
        </ol>
    ) : null;
}
export function OrderCard({
    order,
    priority = false,
}: {
    order: ConsumerOrder;
    priority?: boolean;
}) {
    const href =
        orderDestination(order) === 'checkout' && order.orderId
            ? checkout.url(order.orderId)
            : tracking.url(order.campaign.id);
    return (
        <article className="consumer-order-card">
            <CampaignVisual
                campaign={order.campaign}
                thumbnail
                categoryFallback
                className="consumer-order-photo"
            />
            <div className="min-w-0">
                <h3>
                    <Link href={href}>{order.campaign.title}</Link>
                </h3>
                <p>
                    {order.quantity}{' '}
                    {campaignUnit(order.campaign.unit, order.quantity)} ·{' '}
                    {order.totalCents !== null
                        ? money(order.totalCents)
                        : 'Preço por confirmar'}
                </p>
                <div className="consumer-order-hint">
                    {order.state.deadline ? (
                        <DeadlineLabel
                            deadline={order.state.deadline}
                            compact
                        />
                    ) : order.state.code === 'ready' ? (
                        order.pickupPoint
                    ) : (
                        order.state.description
                    )}
                </div>
            </div>
            <div className="consumer-order-card-actions">
                <OrderStatus state={order.state} />
                <Link
                    className="junta-btn junta-btn-sm junta-btn-outline"
                    href={href}
                >
                    {priority && order.state.primaryAction?.code === 'pay'
                        ? 'Pagar agora'
                        : (order.state.primaryAction?.label ?? 'Ver compra')}
                </Link>
            </div>
        </article>
    );
}
export function CodeDisplay({ code }: { code: string }) {
    const [copied, setCopied] = useState(false);
    return (
        <div id="collection-code" className="consumer-code">
            <code>{code}</code>
            <button
                type="button"
                className="junta-btn junta-btn-sm junta-btn-outline"
                onClick={() => {
                    void navigator.clipboard
                        .writeText(code)
                        .then(() => setCopied(true))
                        .catch(() =>
                            toast.error(
                                'Não foi possível copiar. Seleccione o código para o copiar.',
                            ),
                        );
                }}
            >
                <Copy className="size-4" />
                {copied ? 'Copiado' : 'Copiar'}
            </button>
            <p>Mostre este código ao organizador quando receber o pedido.</p>
        </div>
    );
}
export function MobileActionBar({ children }: { children: ReactNode }) {
    return <div className="consumer-mobile-action">{children}</div>;
}
export { Breadcrumbs as ConsumerBreadcrumbs } from '@/components/junta/PageHeader';
