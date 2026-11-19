import { CheckCircle2, CircleDashed, Clock3, CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { purchaseState } from '@/components/junta/PurchaseForms';

export function MetricStrip({
    items,
}: {
    items: { label: string; value: ReactNode }[];
}) {
    return (
        <dl className="junta-metric-strip">
            {items.map((item) => (
                <div key={item.label}>
                    <dt>{item.label}</dt>
                    <dd>{item.value}</dd>
                </div>
            ))}
        </dl>
    );
}

export function OrderStatus({ status }: { status: string }) {
    const success = [
        'paid',
        'received',
        'refunded',
        'completed',
        'ready',
    ].includes(status);
    const error = ['failed', 'cancelled'].includes(status);
    const waiting = [
        'pending',
        'review',
        'payment_pending',
        'refund_pending',
        'requested',
        'open',
    ].includes(status);
    const Icon = success
        ? CheckCircle2
        : error
          ? CircleAlert
          : waiting
            ? Clock3
            : CircleDashed;
    return (
        <span
            className="junta-order-status"
            data-tone={
                success
                    ? 'success'
                    : error
                      ? 'error'
                      : waiting
                        ? 'warning'
                        : 'neutral'
            }
        >
            <Icon className="size-3.5 shrink-0" aria-hidden="true" />
            {purchaseState(status)}
        </span>
    );
}
