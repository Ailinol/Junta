import { usePage } from '@inertiajs/react';
import type {
    Campaign,
    Notification,
    Participation,
    Proposal,
    SupplierProfile,
    UserProfile,
} from '@/types/junta';

export type OrganizerApplication = {
    id: number;
    userId: number;
    fullName: string;
    email?: string;
    phone: string;
    location: string;
    motivation: string;
    status: 'pending' | 'approved' | 'rejected';
    decisionReason?: string | null;
    reviewerName?: string | null;
    reviewedAt?: string | null;
    createdAt: string;
};
export type AuditRecord = {
    id: number;
    actor: string;
    action: string;
    subject: string;
    subjectId: number;
    changes?: Record<string, unknown>;
    createdAt: string;
};
export type PrototypeStore = {
    isDemo?: boolean;
    campaigns: Campaign[];
    participations: Participation[];
    proposals: Proposal[];
    notifications: Notification[];
    suppliers: SupplierProfile[];
    users: (UserProfile & { roles: string[] })[];
    applications: OrganizerApplication[];
    organizerApplication: OrganizerApplication | null;
    audit: AuditRecord[];
    consumerProfile: {
        phone: string;
        city: string;
        address: string;
        notifProposal: boolean;
        notifReminder: boolean;
        notifPickup: boolean;
    };
    supplierProfile: SupplierProfile;
};
const empty: PrototypeStore = {
    isDemo: false,
    campaigns: [],
    participations: [],
    proposals: [],
    notifications: [],
    suppliers: [],
    users: [],
    applications: [],
    audit: [],
    organizerApplication: null,
    consumerProfile: {
        phone: '',
        city: '',
        address: '',
        notifProposal: true,
        notifReminder: true,
        notifPickup: true,
    },
    supplierProfile: {
        id: 0,
        companyName: '',
        nuit: '',
        contactName: '',
        email: '',
        phone: '',
        location: '',
        categories: [],
        verified: false,
    },
};
/** Compatibility import for existing pages; the source is now the authorised server payload. */
export function usePrototypeStore() {
    const { business } = usePage().props;
    return {
        data: (business as PrototypeStore | undefined) ?? empty,
        isLoaded: !!business,
    };
}
