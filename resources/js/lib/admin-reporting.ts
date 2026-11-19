import type { Campaign, CampaignCategory } from '@/types/junta';
export type ReportMonth = {
    key: string;
    label: string;
    longLabel: string;
    campaigns: number;
    participants: number;
    estimatedVolume: number;
};
export type ReportCategory = {
    key: CampaignCategory;
    campaigns: number;
    participants: number;
    estimatedVolume: number;
    sample: Campaign;
};
export type ReportRegion = {
    label: string;
    campaigns: number;
    participants: number;
    estimatedVolume: number;
};
const monthFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Africa/Maputo',
    year: 'numeric',
    month: '2-digit',
});
function monthKey(value: number | string): string {
    const parts = monthFormatter.formatToParts(new Date(value));
    return (
        parts.find((part) => part.type === 'year')!.value +
        '-' +
        parts.find((part) => part.type === 'month')!.value
    );
}
export function campaignEstimate(campaign: Campaign): number {
    return campaign.estimatedPrice !== undefined &&
        Number.isFinite(campaign.estimatedPrice) &&
        campaign.estimatedPrice > 0
        ? campaign.estimatedPrice * campaign.currentQuantity
        : 0;
}
export function buildAdminReport(
    source: Campaign[],
    period: 3 | 6 | 12,
    now = Date.now(),
) {
    const currentKey = monthKey(now);
    const [year, month] = currentKey.split('-').map(Number);
    const start = new Date(Date.UTC(year, month - period, 1));
    const months: ReportMonth[] = Array.from({ length: period }, (_, index) => {
        const date = new Date(
            Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + index, 1),
        );
        return {
            key: date.toISOString().slice(0, 7),
            label: new Intl.DateTimeFormat('pt-MZ', {
                timeZone: 'Africa/Maputo',
                month: 'short',
            })
                .format(date)
                .replace('.', ''),
            longLabel: new Intl.DateTimeFormat('pt-MZ', {
                timeZone: 'Africa/Maputo',
                month: 'long',
                year: 'numeric',
            }).format(date),
            campaigns: 0,
            participants: 0,
            estimatedVolume: 0,
        };
    });
    const firstKey = months[0].key;
    const campaigns = source.filter(
        (campaign) =>
            Number.isFinite(Date.parse(campaign.createdAt)) &&
            Date.parse(campaign.createdAt) <= now &&
            monthKey(campaign.createdAt) >= firstKey,
    );
    const categoryMap = new Map<CampaignCategory, ReportCategory>();
    const regionMap = new Map<string, ReportRegion>();
    let estimatedVolume = 0;
    let participants = 0;
    let pricedCampaigns = 0;
    for (const campaign of campaigns) {
        const estimate = campaignEstimate(campaign);
        const month = months.find(
            (item) => item.key === monthKey(campaign.createdAt),
        )!;
        month.campaigns++;
        month.participants += campaign.participantCount;
        month.estimatedVolume += estimate;
        estimatedVolume += estimate;
        participants += campaign.participantCount;
        if (
            campaign.estimatedPrice !== undefined &&
            campaign.estimatedPrice > 0 &&
            Number.isFinite(campaign.estimatedPrice)
        )
            pricedCampaigns++;
        const category = categoryMap.get(campaign.category) ?? {
            key: campaign.category,
            campaigns: 0,
            participants: 0,
            estimatedVolume: 0,
            sample: campaign,
        };
        category.campaigns++;
        category.participants += campaign.participantCount;
        category.estimatedVolume += estimate;
        if (!category.sample.imageUrl && campaign.imageUrl)
            category.sample = campaign;
        categoryMap.set(campaign.category, category);
        const location =
            campaign.location.split(',')[0].trim() ||
            'Localização não indicada';
        const region = regionMap.get(location) ?? {
            label: location,
            campaigns: 0,
            participants: 0,
            estimatedVolume: 0,
        };
        region.campaigns++;
        region.participants += campaign.participantCount;
        region.estimatedVolume += estimate;
        regionMap.set(location, region);
    }
    const categories = [...categoryMap.values()].sort(
        (a, b) =>
            b.estimatedVolume - a.estimatedVolume ||
            b.campaigns - a.campaigns ||
            a.key.localeCompare(b.key),
    );
    const regions = [...regionMap.values()].sort(
        (a, b) =>
            b.estimatedVolume - a.estimatedVolume ||
            b.campaigns - a.campaigns ||
            a.label.localeCompare(b.label),
    );
    return {
        campaigns,
        months,
        categories,
        regions,
        participants,
        estimatedVolume,
        pricedCampaigns,
        periodLabel: months[0].longLabel + ' — ' + months.at(-1)!.longLabel,
    };
}
export const reportPhaseLabels: Record<string, string> = {
    draft: 'Rascunho',
    interest: 'Interesse aberto',
    evaluation: 'Em avaliação',
    selection: 'Proposta escolhida',
    confirmed: 'Condições fixadas',
    payment: 'Pagamentos',
    review: 'Em conferência',
    supply: 'Fornecimento',
    completed: 'Concluída',
    cancelled: 'Cancelada',
};
const fallbackPhases: Record<string, string> = {
    rascunho: 'draft',
    ativa: 'interest',
    em_avaliacao: 'evaluation',
    proposta_selecionada: 'selection',
    confirmada: 'confirmed',
    em_fornecimento: 'supply',
    concluida: 'completed',
    cancelada: 'cancelled',
};
export function reportPhases(campaigns: Campaign[]) {
    return Object.entries(reportPhaseLabels)
        .map(([key, label]) => ({
            key,
            label,
            value: campaigns.filter(
                (campaign) =>
                    (campaign.effectiveStage ??
                        fallbackPhases[campaign.status]) === key,
            ).length,
        }))
        .filter((item) => item.value > 0);
}
export function topParticipatedCampaigns(
    campaigns: Campaign[],
    limit = 5,
): Campaign[] {
    return [...campaigns]
        .sort(
            (a, b) =>
                b.participantCount - a.participantCount ||
                b.currentQuantity / Math.max(1, b.targetQuantity) -
                    a.currentQuantity / Math.max(1, a.targetQuantity) ||
                a.id - b.id,
        )
        .slice(0, limit);
}
