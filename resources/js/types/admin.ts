import type { OrganizerCampaign, VisibleState } from '@/types/organizer';
import type {
    PurchaseOrder,
    PurchasePage,
    PurchaseOperations,
} from '@/types/purchase';
export type AdminTask = {
    id: string;
    title: string;
    description: string;
    since: string;
    primaryAction: { label: string; href: string };
};
export type Intervention = {
    id: string;
    campaignId: number;
    orderId: number | null;
    campaignTitle: string;
    participantName: string | null;
    kind: 'payment' | 'refund' | 'issue' | 'ready' | 'balance';
    label: string;
    since: string;
    primaryAction: { label: string; href: string };
};
export type AdminCampaign = OrganizerCampaign & {
    hasPurchase: boolean;
    purchaseMinimum: number;
    canRemove: boolean;
    balanceCents?: number;
};
export type AdminOrder = PurchaseOrder & {
    state: VisibleState & { canVerify: boolean; canResolve: boolean };
};
export type FinanceEvent = {
    id: number;
    campaign_id: number;
    kind: string;
    amount_cents: number;
    reference: string;
    reason: string;
    created_at: string;
};
export type FinanceFilters = {
    search: string;
    campaignId: number | null;
    from: string | null;
    to: string | null;
    type: string | null;
};
export type Closure = {
    canComplete: boolean;
    supplierBalanceCents: number;
    carrierBalanceCents: number;
    blockers: string[];
    items: { key: string; label: string; complete: boolean; href: string }[];
};
export type AuditRecord = {
    id: number;
    action: string;
    actor: string;
    subject: string;
    subjectId: number;
    createdAt: string;
    changes: Record<string, unknown>;
};
export type AdminFinanceProps = {
    interventions: PurchasePage<Intervention>;
    purchaseCampaigns: AdminCampaign[];
    selectedView: AdminCampaign | null;
    selectedCampaignId: number | null;
    selectedOrder: AdminOrder | null;
    orders: PurchasePage<AdminOrder>;
    events: PurchasePage<FinanceEvent>;
    campaignTotals: Record<string, number> | null;
    filteredTotals: Record<string, number>;
    filters: FinanceFilters;
    eventTypes: string[];
    closure: Closure | null;
    selectedOperations: PurchaseOperations | null;
    tab: 'pedidos' | 'movimentos' | 'encerramento';
    orderId: number | null;
    testPayments: boolean;
};
