import type { Campaign } from '@/types/junta';
import type { PurchaseOrder, PurchasePage } from '@/types/purchase';
export type OrganizerAction = {
    code: string;
    label: string;
    target: 'edit' | 'participants' | 'proposals' | 'purchase';
    disabled?: boolean;
};
export type VisibleState = {
    code: string;
    label: string;
    tone: 'neutral' | 'info' | 'warning' | 'success';
    icon: string;
    isFinal: boolean;
    primaryAction: OrganizerAction | null;
    waitingOn: { label: string; since: string | null } | null;
    blocker: string | null;
    description?: string;
    attention?: boolean;
    step?: number;
};
export type Deadline = {
    value: string;
    label: string;
    detail: string;
    isPast: boolean;
};
export type OrganizerWorkspaceState = VisibleState & {
    progress: {
        value: number;
        target: number;
        label: string;
        heading: string;
    } | null;
    deadline: (Deadline & { blocked: boolean }) | null;
    responsible: string;
};
export type OrganizerCampaign = {
    forecastVersion?: string | null;
    workspace: OrganizerWorkspaceState;
    list: {
        section: 'attention' | 'waiting' | 'drafts' | 'closed';
        action: OrganizerAction | null;
        waiting: string | null;
        progress: {
            value: number;
            target: number;
            label: string;
            heading: string;
        } | null;
        deadline: {
            value: string;
            label: string;
            detail: string;
            blocked: boolean;
        } | null;
    };
    campaign: Campaign;
    state: VisibleState;
    deadline: Deadline | null;
    metrics: {
        openIssues: number;
        participants: number;
        interestUnits: number;
        proposals: number;
        openProposals: number;
        paidUnits: number;
        chargedUnits: number;
        readyOrders: number;
        waitingOrders: number;
        receivedOrders: number;
        refundedOrders: number;
        allocatedUnits: number;
        receivedUnits: number;
        unallocatedUnits: number;
        minimumWaitingQuantity: number;
        pendingPayments: number;
        escalatedPayments: number;
        receivedCents: number;
        refundedCents: number;
        supplierCents: number;
    };
    progress: { value: number; target: number; label: string };
    canEdit: boolean;
    editBlockedReason: string | null;
    canCancel: boolean;
    supplierName: string | null;
    deliveryLabel: string;
};
export type OrganizerDashboard = {
    inbox: {
        tasks: OrganizerCampaign[];
        waiting: OrganizerCampaign[];
        count: number;
    };
    tasks: OrganizerCampaign[];
    campaigns: OrganizerCampaign[];
    metrics: {
        active: number;
        awaitingProposals: number;
        payments: number;
        deliveries: number;
    };
};
export type OrganizerFilters = { search: string; phase: string; sort: string };
export type OrganizerCampaignPage = PurchasePage<OrganizerCampaign>;
export type OrganizerParticipant = {
    id: number;
    name: string;
    phone: string;
    quantity: number;
    joinedAt: string;
    notes: string;
    state: VisibleState;
    orderId: number | null;
    amountCents: number | null;
};
export type OrganizerOrder = PurchaseOrder & { state: VisibleState };
export type OrganizerOffer = {
    id: number;
    supplierName: string;
    unitCents: number;
    totalCents: number | null;
    quantity: number;
    minimum: number | null;
    deliveryDays: number;
    transportCents: number | null;
    transportIncluded: boolean | null;
    deliveryLabel: string;
    validity: Deadline | null;
    notes: string;
    coverage: string;
    canSelect: boolean;
    blockedReason: string | null;
    bestPrice: boolean;
    fastest: boolean;
    selected: boolean;
    state: VisibleState;
};
export type OrganizerHistory = {
    id: number;
    label: string;
    actor: string;
    createdAt: string;
};

export type OrganizerCampaignSection = {
    key: OrganizerCampaign['list']['section'];
    label: string;
    collapsed: boolean;
    campaigns: OrganizerCampaignPage;
};
