import { edit, participantes, propostas } from '@/routes/organizador/campanhas';
import { show as operations } from '@/routes/organizador/compra';
import type { Campaign } from '@/types/junta';

export function nextCampaignAction(campaign: Campaign) {
    if (
        campaign.purchasePhase === 'payment' ||
        campaign.purchasePhase === 'review'
    ) {
        return {
            label:
                campaign.purchasePhase === 'review'
                    ? 'Conferir pagamentos pendentes'
                    : 'Acompanhar pagamentos',
            href: operations(campaign.id),
            description:
                'As condições de compra estão fixadas. Acompanhe os pagamentos e confira as tentativas pendentes antes do apuramento.',
        };
    }
    if (campaign.purchasePhase === 'supply') {
        return {
            label: 'Gerir fornecimento e entregas',
            href: operations(campaign.id),
            description:
                'A compra foi apurada. Registe a recepção e acompanhe a separação e entrega dos pedidos pagos.',
        };
    }
    switch (campaign.status) {
        case 'rascunho':
            return {
                label: 'Rever e publicar',
                href: edit(campaign.id),
                description:
                    'Confirme a identificação do produto, a unidade, o objectivo, a localização e o prazo. O rascunho ainda está privado.',
            };
        case 'ativa':
            return new Date(campaign.deadline).getTime() <= Date.now()
                ? {
                      label: 'Fechar interesse',
                      href: operations(campaign.id),
                      description:
                          'O prazo terminou. Confira a procura registada e feche o interesse para avaliar as propostas.',
                  }
                : {
                      label: 'Acompanhar participantes',
                      href: participantes(campaign.id),
                      description:
                          'A campanha recebe interesse. Acompanhe as quantidades e o prazo; depois feche esta etapa na operação de compra.',
                  };
        case 'em_avaliacao':
            return {
                label: 'Comparar propostas',
                href: propostas(campaign.id),
                description:
                    'Compare custo, quantidade, prazo e condições. A selecção de fornecedor exige uma decisão do organizador.',
            };
        case 'proposta_selecionada':
            return {
                label: 'Preparar pagamentos',
                href: operations(campaign.id),
                description:
                    'A proposta escolhida fixa as condições comerciais. Defina a comissão, o prazo e a recolha antes de pedir pagamentos aos participantes.',
            };
        case 'confirmada':
            return {
                label: 'Gerir pagamentos',
                href: operations(campaign.id),
                description:
                    'Acompanhe os pagamentos e apure a quantidade antes de iniciar o fornecimento.',
            };
        case 'em_fornecimento':
            return {
                label: 'Gerir recepção e entregas',
                href: operations(campaign.id),
                description:
                    'Registe a recepção, separe os pedidos e acompanhe a entrega aos participantes.',
            };
        default:
            return {
                label: 'Consultar operação',
                href: operations(campaign.id),
                description:
                    'Consulte os registos e o resultado desta campanha.',
            };
    }
}
