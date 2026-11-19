<?php

namespace App\Support;

class PlatformRules
{
    public const CATEGORIES = ['alimentacao', 'construcao', 'eletronicos', 'escritorio', 'saude', 'agricola', 'servicos', 'outro'];

    public const CAMPAIGN_STATUSES = ['rascunho', 'ativa', 'em_avaliacao', 'proposta_selecionada', 'confirmada', 'em_fornecimento', 'concluida', 'cancelada'];

    public const PROPOSAL_STATUSES = ['rascunho', 'submetida', 'em_avaliacao', 'recusada', 'expirada', 'retirada'];

    public const DELIVERY_MODES = ['recolha', 'entrega', 'ambos'];
}
