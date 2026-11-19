import {
    edit,
    participantes,
    propostas,
    show,
} from '@/routes/organizador/campanhas';
import { show as purchase } from '@/routes/organizador/compra';
import type { OrganizerAction } from '@/types/organizer';
export function organizerActionUrl(
    id: number,
    action: OrganizerAction | null,
): string {
    if (!action) return show.url(id);
    if (action.target === 'edit') return edit.url(id);
    if (action.target === 'participants') return participantes.url(id);
    if (action.target === 'proposals') return propostas.url(id);
    const anchor = ['orders', 'handoff'].includes(action.code)
        ? '#purchase-orders'
        : '#current-task';
    return purchase.url(id) + anchor;
}
