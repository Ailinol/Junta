import type { CampaignCategory } from '@/types/junta';
export const campaignCategories: Record<CampaignCategory, string> = {
    alimentacao: 'Alimentação',
    construcao: 'Construção',
    eletronicos: 'Electrónica',
    escritorio: 'Escritório',
    saude: 'Saúde',
    agricola: 'Agrícola',
    servicos: 'Serviços',
    outro: 'Outros',
};
const singularUnits: Record<string, string> = {
    sacos: 'saco',
    litros: 'litro',
    unidades: 'unidade',
    caixas: 'caixa',
    kits: 'kit',
    paletes: 'palete',
    toneladas: 'tonelada',
    kg: 'kg',
};
export function campaignUnit(unit: string, quantity = 1): string {
    return quantity === 1 ? (singularUnits[unit.toLowerCase()] ?? unit) : unit;
}
export function campaignTitle(title: string): string {
    return title.replace(
        /^(?:Compra|Aquisição) (?:coletiva|colectiva) de /i,
        '',
    );
}
export function campaignDate(value: string): string {
    return new Date(value).toLocaleDateString('pt-PT', {
        day: 'numeric',
        month: 'long',
        timeZone: 'Africa/Maputo',
    });
}
export function deliveryLabel(mode: 'recolha' | 'entrega' | 'ambos'): string {
    return mode === 'ambos'
        ? 'Entrega ou recolha'
        : mode === 'entrega'
          ? 'Entrega'
          : 'Recolha';
}
export function campaignReference(campaign: {
    id: number;
    reference?: string;
}): string {
    return campaign.reference ?? `JNT-${String(campaign.id).padStart(6, '0')}`;
}
