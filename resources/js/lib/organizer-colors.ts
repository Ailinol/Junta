import type { Deadline, VisibleState } from '@/types/organizer';

export type OrganizerColor =
    | 'brand'
    | 'success'
    | 'success-deep'
    | 'warning'
    | 'danger'
    | 'info'
    | 'neutral'
    | 'refunded';
type ColorState = Pick<VisibleState, 'tone'> &
    Partial<Pick<VisibleState, 'code' | 'step'>>;
const orderColors: Record<string, OrganizerColor> = {
    payment: 'warning',
    payment_pending: 'info',
    paid: 'success',
    ready: 'brand',
    completed: 'success-deep',
    refunded: 'refunded',
    cancelled: 'neutral',
    expired: 'danger',
    issue: 'danger',
    refund_pending: 'info',
    interest: 'brand',
    cancelled_interest: 'neutral',
    aceite: 'success',
    submetida: 'info',
    em_avaliacao: 'info',
    recusada: 'neutral',
    retirada: 'neutral',
    expirada: 'danger',
    rascunho: 'neutral',
};
const campaignColors: Record<string, OrganizerColor> = {
    draft: 'neutral',
    interest: 'brand',
    evaluation: 'info',
    selection: 'info',
    confirmed: 'brand',
    payment: 'brand',
    review: 'warning',
    supply: 'brand',
    completed: 'success-deep',
    cancelled: 'neutral',
};
export function organizerStateColor(state: ColorState): OrganizerColor {
    if (state.step !== undefined)
        return state.tone === 'warning' &&
            !['completed', 'cancelled'].includes(state.code ?? '')
            ? 'warning'
            : (campaignColors[state.code ?? ''] ?? state.tone);
    return orderColors[state.code ?? ''] ?? state.tone;
}
export function organizerProgressColor(
    value: number,
    target: number,
    deadline: Deadline | null | undefined,
    now: number | null,
): OrganizerColor {
    if (target > 0 && value >= target) return 'success';
    if (deadline?.isPast) return 'warning';
    if (deadline && now !== null) {
        const remaining = Date.parse(deadline.value) - now;
        if (remaining > 0 && remaining < 24 * 60 * 60 * 1000) return 'warning';
    }
    return 'brand';
}
