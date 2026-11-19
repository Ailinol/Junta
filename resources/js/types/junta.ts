// Perfis de utilizador
export type UserRole = 'consumidor' | 'organizador' | 'fornecedor' | 'admin';

export type UserProfile = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    avatar?: string;
    createdAt: string;
};

// Estados das campanhas
export type CampaignStatus =
    | 'rascunho'
    | 'ativa'
    | 'em_avaliacao'
    | 'proposta_selecionada'
    | 'confirmada'
    | 'em_fornecimento'
    | 'concluida'
    | 'cancelada';

export type CampaignCategory =
    | 'alimentacao'
    | 'construcao'
    | 'eletronicos'
    | 'escritorio'
    | 'saude'
    | 'agricola'
    | 'servicos'
    | 'outro';

export type Campaign = {
    effectiveStage?: string;
    purchasePhase?:
        | 'interest'
        | 'payment'
        | 'review'
        | 'supply'
        | 'completed'
        | 'cancelled';
    paymentDeadline?: string | null;
    acceptsProposals?: boolean;
    reference?: string;
    canEdit?: boolean;
    isDemo?: boolean;
    organizerId?: number | null;
    id: number;
    title: string;
    description: string;
    category: CampaignCategory;
    status: CampaignStatus;
    organizerName: string;
    targetQuantity: number;
    currentQuantity: number;
    unit: string;
    participantCount: number;
    deadline: string;
    location: string;
    deliveryMode: 'recolha' | 'entrega' | 'ambos';
    estimatedPrice?: number;
    imageUrl?: string;
    createdAt: string;
    updatedAt: string;
    tags?: string[];
};

// Participação do consumidor
export type ParticipationStatus = 'pendente' | 'confirmada' | 'cancelada';

export type Participation = {
    id: number;
    campaignId: number;
    campaign: Campaign;
    quantity: number;
    status: ParticipationStatus;
    joinedAt: string;
    notes?: string;
};

// Proposta do fornecedor
export type ProposalStatus =
    | 'retirada'
    | 'rascunho'
    | 'submetida'
    | 'em_avaliacao'
    | 'aceite'
    | 'recusada'
    | 'expirada';

export type Proposal = {
    supplierVerified?: boolean;
    id: number;
    campaignId: number;
    campaign: Campaign;
    supplierId: number;
    supplierName: string;
    unitPrice: number;
    availableQuantity: number;
    minimumQuantity?: number | null;
    reservedUntil?: string | null;
    transportIncluded?: boolean | null;
    transportUnitPrice?: number | null;
    totalEstimated: number;
    deliveryDays: number;
    deliveryMode: 'recolha' | 'entrega' | 'ambos';
    validUntil: string;
    status: ProposalStatus;
    notes?: string;
    submittedAt: string;
};

// Perfil do fornecedor
export type SupplierProfile = {
    userId?: number | null;
    id: number;
    companyName: string;
    nuit: string;
    contactName: string;
    email: string;
    phone: string;
    location: string;
    categories: CampaignCategory[];
    description?: string;
    verified: boolean;
};

// Notificação
export type NotificationType = 'info' | 'success' | 'warning' | 'error';

export type Notification = {
    id: number | string;
    type: NotificationType;
    title: string;
    message: string;
    read: boolean;
    createdAt: string;
    link?: string;
};

// Paginação
export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

// Filtros de campanha
export type CampaignFilters = {
    search?: string;
    category?: CampaignCategory | '';
    status?: CampaignStatus | '';
    sortBy?: 'deadline' | 'quantity' | 'recent' | 'progress';
};

// Stats do dashboard
export type ConsumidorStats = {
    activeCampaigns: number;
    totalParticipations: number;
    completedPurchases: number;
    pendingConfirmations: number;
};

export type OrganizadorStats = {
    totalCampaigns: number;
    activeCampaigns: number;
    totalQuantityAggregated: number;
    campaignsNearDeadline: number;
    completedCampaigns: number;
    pendingProposals: number;
};

export type FornecedorStats = {
    availableOpportunities: number;
    submittedProposals: number;
    pendingProposals: number;
    acceptedProposals: number;
    rejectedProposals: number;
};

export type AdminStats = {
    totalUsers: number;
    totalCampaigns: number;
    activeCampaigns: number;
    totalSuppliers: number;
    totalProposals: number;
    campaignsNeedingReview: number;
};
