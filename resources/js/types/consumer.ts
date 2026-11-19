import type { Campaign } from '@/types/junta';
import type { Deadline } from '@/types/organizer';
export type ConsumerState = {
    code: string;
    label: string;
    tone: 'neutral' | 'success' | 'warning';
    isFinal: boolean;
    primaryAction: { code: 'pay' | 'collection'; label: string } | null;
    steps: { label: string; status: 'complete' | 'current' | 'upcoming' }[];
    deadline: Deadline | null;
    description: string;
    canEditInterest: boolean;
    canReportIssue: boolean;
};
export type ConsumerOrder = {
    id: number;
    campaign: Campaign;
    quantity: number;
    orderId: number | null;
    totalCents: number | null;
    pickupPoint: string | null;
    state: ConsumerState;
};
export type PaymentMethod = {
    code: string;
    label: string;
    requiresPhone: boolean;
};
