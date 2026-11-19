import type { PaymentMethod, ConsumerOrder } from '@/types/consumer';
export function localPhone(value: string): string {
    const digits = value.replace(/\D/g, '');
    return (
        value.trim().startsWith('+258') ||
        (digits.length > 9 && digits.startsWith('258'))
            ? digits.slice(3)
            : digits
    ).slice(0, 9);
}
export function paymentFormReady(
    data: {
        paymentMethod: string;
        paymentPhone: string;
        acceptTerms: boolean;
        deliveryMethod: string;
        deliveryAddress: string;
    },
    methods: PaymentMethod[],
): boolean {
    const method = methods.find((entry) => entry.code === data.paymentMethod);
    return (
        !!method &&
        (!method.requiresPhone || /^\+258[0-9]{9}$/.test(data.paymentPhone)) &&
        data.acceptTerms &&
        (data.deliveryMethod !== 'entrega' ||
            data.deliveryAddress.trim().length > 0)
    );
}
export function orderDestination(
    order: ConsumerOrder,
): 'checkout' | 'tracking' {
    return order.orderId &&
        ['payment', 'payment_pending'].includes(order.state.code)
        ? 'checkout'
        : 'tracking';
}
export function humanDeadline(
    value: string,
    current = Date.now(),
): { value: string; label: string; detail: string; isPast: boolean } {
    const date = new Date(value);
    const localDay = (instant: Date) =>
        Date.parse(
            instant.toLocaleDateString('en-CA', { timeZone: 'Africa/Maputo' }),
        );
    const days = Math.round(
        (localDay(date) - localDay(new Date(current))) / 86400000,
    );
    const isPast = date.getTime() <= current;
    const label = isPast
        ? days === 0
            ? 'Terminou hoje'
            : days === -1
              ? 'Terminou ontem'
              : `Terminou há ${Math.abs(days)} dias`
        : days === 0
          ? 'Termina hoje'
          : days === 1
            ? 'Termina amanhã'
            : `Termina em ${days} dias`;
    return {
        value,
        label,
        detail:
            date.toLocaleString('pt-PT', {
                timeZone: 'Africa/Maputo',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }) + ' · Maputo',
        isPast,
    };
}
