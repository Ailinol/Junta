import type { PrototypeStore } from '@/hooks/use-prototype-store';
export function addDemoParticipation(
    current: PrototypeStore,
    campaignId: number,
    quantity: number,
    notes: string,
): PrototypeStore {
    const campaign = current.campaigns.find((c) => c.id === campaignId);
    if (
        !campaign ||
        campaign.status !== 'ativa' ||
        new Date(campaign.deadline).getTime() <= Date.now() ||
        !Number.isSafeInteger(quantity) ||
        quantity <= 0
    )
        throw new Error('Participação indisponível');
    const updated = {
        ...campaign,
        currentQuantity: campaign.currentQuantity + quantity,
        participantCount: campaign.participantCount + 1,
    };
    return {
        ...current,
        campaigns: current.campaigns.map((c) =>
            c.id === campaign.id ? updated : c,
        ),
        participations: [
            {
                id: Math.max(0, ...current.participations.map((p) => p.id)) + 1,
                campaignId: campaign.id,
                campaign: updated,
                quantity,
                status: 'pendente',
                joinedAt: new Date().toISOString(),
                notes,
            },
            ...current.participations,
        ],
    };
}
