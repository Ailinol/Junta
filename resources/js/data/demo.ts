import type {
    Campaign,
    Notification,
    Participation,
    Proposal,
    SupplierProfile,
    UserProfile,
} from '@/types/junta';

// ─── Campanhas demo ───────────────────────────────────────────────────────────

export const demoCampaigns: Campaign[] = [
    {
        id: 1,
        title: 'Compra Coletiva de Cimento Portland',
        description:
            'Agrupamento de compras de cimento Portland 50 kg para obras de construção em Maputo. Desconto estimado de 20% face ao preço de mercado.',
        category: 'construcao',
        status: 'ativa',
        organizerName: 'Carlos Mondlane',
        targetQuantity: 500,
        currentQuantity: 320,
        unit: 'sacos',
        participantCount: 18,
        deadline: '2026-11-15T23:59:00Z',
        location: 'Maputo, Bairro da Sommerschield',
        deliveryMode: 'recolha',
        estimatedPrice: 850,
        createdAt: '2026-10-01T08:00:00Z',
        updatedAt: '2026-10-20T14:30:00Z',
        tags: ['cimento', 'construção', 'maputo'],
    },
    {
        id: 2,
        title: 'Azeite de Coco Prensado a Frio – Beira',
        description:
            'Aquisição coletiva de azeite de coco prensado a frio, produzido localmente na Beira. Produto 100% natural, sem aditivos.',
        category: 'alimentacao',
        status: 'em_avaliacao',
        organizerName: 'Fátima Nhantumbo',
        targetQuantity: 200,
        currentQuantity: 200,
        unit: 'litros',
        participantCount: 35,
        deadline: '2026-10-30T23:59:00Z',
        location: 'Beira, Sofala',
        deliveryMode: 'ambos',
        estimatedPrice: 320,
        createdAt: '2026-09-15T09:00:00Z',
        updatedAt: '2026-10-22T10:00:00Z',
        tags: ['azeite de coco', 'alimentação', 'beira', 'natural'],
    },
    {
        id: 3,
        title: 'Painéis Solares 300W para Habitação',
        description:
            'Compra coletiva de painéis solares fotovoltaicos 300W monocristalinos para habitação urbana e periurbana. Inclui controlador de carga.',
        category: 'eletronicos',
        status: 'proposta_selecionada',
        organizerName: 'João Sitoe',
        targetQuantity: 100,
        currentQuantity: 100,
        unit: 'unidades',
        participantCount: 52,
        deadline: '2026-10-25T23:59:00Z',
        location: 'Matola, Maputo',
        deliveryMode: 'entrega',
        estimatedPrice: 12500,
        createdAt: '2026-09-01T07:00:00Z',
        updatedAt: '2026-10-21T16:45:00Z',
        tags: ['solar', 'energia', 'matola', 'eletrónica'],
    },
    {
        id: 4,
        title: 'Sementes de Milho Híbrido – Temporada 2026/27',
        description:
            'Aquisição coletiva de sementes de milho híbrido certificadas para a temporada agrícola 2026/27. Variedade de alta produtividade adaptada ao clima moçambicano.',
        category: 'agricola',
        status: 'confirmada',
        organizerName: 'Ana Cossa',
        targetQuantity: 1000,
        currentQuantity: 850,
        unit: 'kg',
        participantCount: 67,
        deadline: '2026-11-01T23:59:00Z',
        location: 'Chimoio, Manica',
        deliveryMode: 'recolha',
        estimatedPrice: 180,
        createdAt: '2026-08-20T06:00:00Z',
        updatedAt: '2026-10-19T11:00:00Z',
        tags: ['sementes', 'milho', 'agricultura', 'chimoio'],
    },
    {
        id: 5,
        title: 'Material de Escritório – Empresas da Baixa',
        description:
            'Compra coletiva de material de escritório (resmas A4, canetas, tinteiros, pastas) para pequenas empresas localizadas na Baixa de Maputo.',
        category: 'escritorio',
        status: 'ativa',
        organizerName: 'Pedro Tembe',
        targetQuantity: 50,
        currentQuantity: 22,
        unit: 'kits',
        participantCount: 11,
        deadline: '2026-11-20T23:59:00Z',
        location: 'Maputo, Baixa',
        deliveryMode: 'entrega',
        estimatedPrice: 2500,
        createdAt: '2026-10-10T10:00:00Z',
        updatedAt: '2026-10-22T08:00:00Z',
        tags: ['escritório', 'material', 'maputo'],
    },
    {
        id: 6,
        title: 'Medicamentos Genéricos – Clínicas Comunitárias',
        description:
            'Agrupamento de compras de medicamentos genéricos essenciais para clínicas comunitárias em Nampula. Foco em analgésicos, antibióticos e antimaláricos.',
        category: 'saude',
        status: 'concluida',
        organizerName: 'Dr. Celso Macamo',
        targetQuantity: 300,
        currentQuantity: 300,
        unit: 'caixas',
        participantCount: 12,
        deadline: '2026-09-30T23:59:00Z',
        location: 'Nampula',
        deliveryMode: 'entrega',
        estimatedPrice: 450,
        createdAt: '2026-08-01T08:00:00Z',
        updatedAt: '2026-10-05T09:00:00Z',
        tags: ['medicamentos', 'saúde', 'nampula'],
    },
];

// ─── Participações demo ───────────────────────────────────────────────────────

export const demoParticipations: Participation[] = [
    {
        id: 1,
        campaignId: 1,
        campaign: demoCampaigns[0],
        quantity: 20,
        status: 'confirmada',
        joinedAt: '2026-10-05T10:00:00Z',
        notes: 'Para obra na Av. Julius Nyerere.',
    },
    {
        id: 2,
        campaignId: 2,
        campaign: demoCampaigns[1],
        quantity: 5,
        status: 'pendente',
        joinedAt: '2026-10-12T09:30:00Z',
    },
    {
        id: 3,
        campaignId: 3,
        campaign: demoCampaigns[2],
        quantity: 2,
        status: 'confirmada',
        joinedAt: '2026-09-20T14:00:00Z',
        notes: 'Casa própria em Matola Rio.',
    },
    {
        id: 4,
        campaignId: 4,
        campaign: demoCampaigns[3],
        quantity: 50,
        status: 'confirmada',
        joinedAt: '2026-09-10T07:00:00Z',
    },
    {
        id: 5,
        campaignId: 5,
        campaign: demoCampaigns[4],
        quantity: 1,
        status: 'pendente',
        joinedAt: '2026-10-14T11:00:00Z',
        notes: 'Kit para escritório com 4 pessoas.',
    },
];

// ─── Propostas demo ───────────────────────────────────────────────────────────

export const demoProposals: Proposal[] = [
    {
        id: 1,
        campaignId: 1,
        campaign: demoCampaigns[0],
        supplierId: 1,
        supplierName: 'Cimentos de Moçambique Lda.',
        unitPrice: 780,
        availableQuantity: 1000,
        totalEstimated: 249600,
        deliveryDays: 7,
        deliveryMode: 'recolha',
        validUntil: '2026-11-10T23:59:00Z',
        status: 'em_avaliacao',
        notes: 'Preço inclui IVA. Recolha no nosso armazém em Machava.',
        submittedAt: '2026-10-18T09:00:00Z',
    },
    {
        id: 2,
        campaignId: 1,
        campaign: demoCampaigns[0],
        supplierId: 2,
        supplierName: 'BuildMoz Materiais de Construção',
        unitPrice: 810,
        availableQuantity: 600,
        totalEstimated: 259200,
        deliveryDays: 5,
        deliveryMode: 'ambos',
        validUntil: '2026-11-12T23:59:00Z',
        status: 'submetida',
        notes: 'Entrega disponível para encomendas acima de 50 sacos.',
        submittedAt: '2026-10-19T11:30:00Z',
    },
    {
        id: 3,
        campaignId: 2,
        campaign: demoCampaigns[1],
        supplierId: 3,
        supplierName: 'Cooperativa Coco Verde Beira',
        unitPrice: 290,
        availableQuantity: 500,
        totalEstimated: 58000,
        deliveryDays: 3,
        deliveryMode: 'ambos',
        validUntil: '2026-10-28T23:59:00Z',
        status: 'aceite',
        notes: 'Produto certificado orgânico. Embalagem em garrafa de vidro 1L.',
        submittedAt: '2026-10-10T08:00:00Z',
    },
    {
        id: 4,
        campaignId: 3,
        campaign: demoCampaigns[2],
        supplierId: 4,
        supplierName: 'SolarTech Moçambique',
        unitPrice: 11800,
        availableQuantity: 200,
        totalEstimated: 1180000,
        deliveryDays: 14,
        deliveryMode: 'entrega',
        validUntil: '2026-10-24T23:59:00Z',
        status: 'aceite',
        notes: 'Inclui controlador de carga 30A MPPT e garantia de 25 anos no painel.',
        submittedAt: '2026-09-25T10:00:00Z',
    },
    {
        id: 5,
        campaignId: 4,
        campaign: demoCampaigns[3],
        supplierId: 5,
        supplierName: 'AgroSementes Chimoio',
        unitPrice: 165,
        availableQuantity: 5000,
        totalEstimated: 165000,
        deliveryDays: 10,
        deliveryMode: 'recolha',
        validUntil: '2026-10-31T23:59:00Z',
        status: 'aceite',
        notes: 'Sementes tratadas com fungicida. Germinação garantida ≥ 95%.',
        submittedAt: '2026-09-05T07:00:00Z',
    },
];

// ─── Utilizadores demo ────────────────────────────────────────────────────────

export const demoUsers: UserProfile[] = [
    {
        id: 1,
        name: 'Maria Guambe',
        email: 'maria.guambe@example.com',
        role: 'consumidor',
        createdAt: '2026-01-15T00:00:00Z',
    },
    {
        id: 2,
        name: 'Carlos Mondlane',
        email: 'carlos.mondlane@example.com',
        role: 'organizador',
        createdAt: '2026-01-10T00:00:00Z',
    },
    {
        id: 3,
        name: 'Roberto Zunguze',
        email: 'roberto.zunguze@solartechmz.com',
        role: 'fornecedor',
        createdAt: '2026-02-01T00:00:00Z',
    },
    {
        id: 4,
        name: 'Isabel Cossa',
        email: 'isabel.cossa@junta.co.mz',
        role: 'admin',
        createdAt: '2025-12-01T00:00:00Z',
    },
    {
        id: 5,
        name: 'Tomás Machava',
        email: 'tomas.machava@example.com',
        role: 'consumidor',
        createdAt: '2026-03-05T00:00:00Z',
    },
];

// ─── Perfil de fornecedor demo ────────────────────────────────────────────────

export const demoSupplierProfile: SupplierProfile = {
    id: 1,
    companyName: 'SolarTech Moçambique',
    nuit: '400123456',
    contactName: 'Roberto Zunguze',
    email: 'roberto.zunguze@solartechmz.com',
    phone: '+258 84 123 4567',
    location: 'Matola, Maputo',
    categories: ['eletronicos', 'outro'],
    description:
        'Empresa líder em soluções de energia solar em Moçambique. Importamos e distribuímos painéis solares, baterias e sistemas fotovoltaicos para habitação e indústria desde 2018.',
    verified: true,
};

export const demoSupplier = demoSupplierProfile;

// ─── Notificações demo ────────────────────────────────────────────────────────

export const demoNotifications: Notification[] = [
    {
        id: 1,
        type: 'success',
        title: 'Participação confirmada',
        message:
            'A sua participação na campanha "Painéis Solares 300W" foi confirmada.',
        read: false,
        createdAt: '2026-10-22T09:00:00Z',
        link: '/consumidor/acompanhamento/3',
    },
    {
        id: 2,
        type: 'info',
        title: 'Nova proposta recebida',
        message:
            'A campanha "Cimento Portland" recebeu uma nova proposta da BuildMoz.',
        read: false,
        createdAt: '2026-10-19T11:30:00Z',
        link: '/organizador/campanhas/1/propostas',
    },
    {
        id: 3,
        type: 'warning',
        title: 'Prazo a terminar',
        message:
            'A campanha "Azeite de Coco" termina em 8 dias. Confirme a sua participação.',
        read: false,
        createdAt: '2026-10-22T08:00:00Z',
        link: '/consumidor/acompanhamento/2',
    },
    {
        id: 4,
        type: 'success',
        title: 'Proposta aceite',
        message:
            'A sua proposta para "Painéis Solares 300W" foi aceite pelo organizador.',
        read: true,
        createdAt: '2026-10-21T16:45:00Z',
        link: '/fornecedor/propostas',
    },
    {
        id: 5,
        type: 'info',
        title: 'Nova oportunidade de negócio',
        message:
            'Existe uma nova campanha de eletrónica em Matola que pode ser do seu interesse.',
        read: true,
        createdAt: '2026-10-20T10:00:00Z',
        link: '/fornecedor/oportunidades',
    },
    {
        id: 6,
        type: 'success',
        title: 'Campanha concluída',
        message:
            'A campanha "Medicamentos Genéricos – Nampula" foi concluída com sucesso.',
        read: true,
        createdAt: '2026-10-05T09:00:00Z',
        link: '/organizador/campanhas/6',
    },
    {
        id: 7,
        type: 'error',
        title: 'Campanha cancelada',
        message:
            'A campanha não atingiu o mínimo necessário e foi cancelada pelo organizador.',
        read: true,
        createdAt: '2026-10-03T14:00:00Z',
    },
    {
        id: 8,
        type: 'info',
        title: 'Bem-vindo ao Junta!',
        message:
            'Explore as campanhas ativas e junte-se a compras coletivas vantajosas.',
        read: true,
        createdAt: '2026-10-01T08:00:00Z',
        link: '/campanhas',
    },
    {
        id: 9,
        type: 'warning',
        title: 'Perfil incompleto',
        message:
            'Complete o seu perfil comercial para aumentar a confiança dos organizadores.',
        read: false,
        createdAt: '2026-10-22T07:00:00Z',
        link: '/fornecedor/perfil',
    },
    {
        id: 10,
        type: 'success',
        title: 'Utilizador verificado',
        message:
            'O fornecedor "Cimentos de Moçambique Lda." foi verificado com sucesso.',
        read: true,
        createdAt: '2026-10-18T12:00:00Z',
        link: '/admin/fornecedores',
    },
];

// ─── Helper functions ─────────────────────────────────────────────────────────

export function getCampaignById(id: number): Campaign | undefined {
    return demoCampaigns.find((c) => c.id === id);
}

export function getCampaignsByStatus(status: Campaign['status']): Campaign[] {
    return demoCampaigns.filter((c) => c.status === status);
}

export function getProposalsByCampaign(campaignId: number): Proposal[] {
    return demoProposals.filter((p) => p.campaignId === campaignId);
}
