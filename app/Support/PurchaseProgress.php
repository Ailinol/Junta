<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\PurchaseIssue;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use Carbon\CarbonImmutable;

/** Read-only guidance; mutations always recheck permissions and current state. */
class PurchaseProgress
{
    public function __construct(private PaymentGateway $gateway) {}

    /** @return array{code:string,title:string,actor:string,description:string,action:?string} */
    private function step(string $code, string $title, string $actor, string $description, ?string $action = null): array
    {
        return compact('code', 'title', 'actor', 'description', 'action');
    }

    public function paymentBlockedReason(Campaign $campaign): ?string
    {
        if (! $this->gateway->available()) {
            return 'O pagamento está indisponível. A administração deve verificar a configuração.';
        }
        if ($campaign->purchase_phase !== 'payment') {
            return 'O pagamento não está aberto nesta fase. Aguarde a acção do organizador.';
        }
        $config = $campaign->purchase_config;
        if (! $config || ! $campaign->payment_deadline?->isFuture()) {
            return 'O prazo de pagamento terminou. O sistema vai verificar o mínimo da compra.';
        }
        if (! CarbonImmutable::parse($config['price_valid_until'])->isFuture() || ! CarbonImmutable::parse($config['reserved_until'])->isFuture()) {
            return 'As condições da compra expiraram. Não será iniciado um pagamento.';
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function order(PurchaseOrder $order, bool $owner = true): array
    {
        $order->loadMissing(['campaign', 'attempts.refund', 'issues']);
        $campaign = $order->campaign;
        $uncertain = $order->attempts->contains(fn (PaymentAttempt $attempt): bool => in_array($attempt->status, ['pending', 'review'], true));
        $blocked = $this->paymentBlockedReason($campaign);
        $code = match (true) {
            $order->status === 'refunded' => 'refunded',
            $order->status === 'refund_pending' => 'refund_pending',
            $order->status === 'cancelled' => 'cancelled',
            $order->issues->contains('status', 'open') => 'issue',
            $order->status === 'paid' && $order->receipt_status === 'received' => 'completed',
            $order->status === 'paid' && $order->receipt_status === 'ready' => 'ready',
            $order->status === 'paid' => 'paid',
            $uncertain || $order->status === 'payment_pending' => 'payment_pending',
            ! $order->payment_deadline->isFuture() => 'expired',
            $campaign->purchase_phase === 'cancelled' => 'cancelled',
            default => 'payment',
        };
        $terminal = in_array($code, ['refunded', 'cancelled', 'completed'], true);
        $titles = $owner ? [
            'payment' => 'A pagar', 'payment_pending' => 'A confirmar no telemóvel',
            'paid' => $campaign->purchase_phase === 'supply' ? 'Pago' : 'Pago, a aguardar o mínimo',
            'ready' => 'Pronto para levantar', 'completed' => 'Concluído', 'refunded' => 'Reembolsado',
            'cancelled' => 'Cancelado', 'expired' => 'Prazo de pagamento terminado',
            'issue' => 'Problema reportado', 'refund_pending' => 'Reembolso em processamento',
        ] : [
            'payment' => 'Por pagar', 'payment_pending' => 'A confirmar', 'paid' => 'Pago',
            'ready' => 'Pronto', 'completed' => 'Entregue', 'refunded' => 'Reembolsado',
            'cancelled' => 'Cancelado', 'expired' => 'Prazo terminado', 'issue' => 'Problema reportado',
            'refund_pending' => 'Reembolso em processamento',
        ];
        $mobile = $order->attempts->first()?->payment_method !== 'card';
        if ($owner && $code === 'payment_pending' && ! $mobile) {
            $titles[$code] = 'A confirmar o pagamento';
        }
        $description = match ($code) {
            'payment' => $blocked ?? 'Escolha como pagar e aceite as condições. Só ao iniciar o pagamento será verificada a disponibilidade.',
            'payment_pending' => $mobile ? 'Confirme o pedido no seu telemóvel. Estamos a verificar o resultado; não volte a pagar.' : 'Estamos a verificar o resultado do pagamento. Não volte a pagar.',
            'paid' => $campaign->purchase_phase === 'supply' ? 'O mínimo foi atingido. A mercadoria está a caminho; avisaremos quando puder receber o seu pedido.' : 'Se o mínimo não for atingido, recebe de volta o valor integral, incluindo comissão e transporte.',
            'ready' => 'Apresente este código apenas quando receber a mercadoria no ponto ou na morada acordada.',
            'completed' => 'A recepção do seu pedido foi confirmada. O código já foi utilizado.',
            'refunded' => 'O valor integral foi devolvido.',
            'refund_pending' => 'A devolução integral está a ser tratada. A administração acompanha o pagamento; não volte a pagar.',
            'cancelled' => 'Este pedido foi encerrado e não tem acções pendentes.',
            'expired' => 'Já não é possível iniciar este pagamento. O sistema verifica os pagamentos confirmados e o mínimo da compra.',
            'issue' => 'A administração está a analisar o problema com o seu pedido.',
        };
        $canPay = $code === 'payment' && $blocked === null && (int) ($campaign->purchase_config['available_quantity'] ?? 0) - $campaign->held_quantity >= $order->quantity;
        if ($code === 'payment' && ! $canPay && $blocked === null) {
            $blocked = 'A quantidade disponível não cobre o seu pedido. Não foi iniciado um pagamento.';
        }
        $action = $owner ? ($canPay ? 'pay' : ($code === 'ready' ? 'collection' : null)) : match (true) {
            $campaign->purchase_phase === 'supply' && $code === 'paid' && $order->receipt_status === 'waiting' => 'prepare',
            $campaign->purchase_phase === 'supply' && $code === 'ready' => 'handoff',
            default => null,
        };

        return [...$this->step($code, $titles[$code], $terminal ? 'Sem acção pendente' : 'Sistema Junta', $description, $action),
            'terminal' => $terminal, 'canPay' => $canPay, 'blockedReason' => $code === 'payment' ? $blocked : null,
            'showDeadline' => in_array($code, ['payment', 'payment_pending', 'paid'], true) && $campaign->purchase_phase === 'payment',
            'showMinimum' => $code === 'paid' && in_array($campaign->purchase_phase, ['payment', 'review'], true),
            'poll' => in_array($code, ['payment', 'payment_pending', 'paid', 'ready', 'refund_pending', 'issue'], true),
            'canReportIssue' => $order->status === 'paid' && ! $order->issues->contains('status', 'open') && ! $terminal,
        ];
    }

    /** @return array<string,mixed> */
    public function consumerState(Campaign $campaign, Participation $participation, ?PurchaseOrder $order): array
    {
        $presentation = $order ? $this->order($order) : null;
        $code = $presentation['code'] ?? ($participation->status === 'cancelada' || $campaign->effectiveStage() === 'cancelled' ? 'cancelled' : ($campaign->effectiveStage() === 'completed' ? 'completed' : 'interest'));
        $final = in_array($code, ['completed', 'refunded', 'cancelled', 'expired'], true);
        $label = match ($code) {
            'paid' => 'Pago', 'expired' => 'Prazo terminado', 'ready' => $order?->delivery_method === 'entrega' ? 'Pronto para entrega' : 'Pronto para levantar',
            'interest' => 'Interesse registado', default => $presentation['title'] ?? 'Interesse registado',
        };
        $action = ! $final && ($presentation['canPay'] ?? false) ? ['code' => 'pay', 'label' => 'Pagar'] : (! $final && $code === 'ready' ? ['code' => 'collection', 'label' => 'Ver código'] : null);
        $current = match ($code) {
            'completed' => 5, 'ready' => 4, 'paid', 'issue' => in_array($campaign->purchase_phase, ['supply', 'completed'], true) ? 3 : 2, 'payment', 'payment_pending', 'expired' => 1, default => 0
        };
        $steps = [];
        if (! in_array($code, ['refunded', 'refund_pending', 'cancelled', 'expired'], true)) {
            foreach (['Interesse registado', 'Pagamento', 'Mínimo atingido', 'Mercadoria a caminho', $order?->delivery_method === 'entrega' ? 'Pronto para entrega' : 'Pronto para levantar'] as $index => $title) {
                $steps[] = ['label' => $index === 2 && $current === 2 ? 'A aguardar o mínimo' : $title, 'status' => $index < $current ? 'complete' : ($index === $current ? 'current' : 'upcoming')];
            }
        }
        $deadline = null;
        if (! $final && in_array($code, ['interest', 'payment', 'payment_pending'], true)) {
            $value = $order !== null ? $order->payment_deadline : $campaign->deadline;
            $date = CarbonImmutable::parse($value)->setTimezone('Africa/Maputo');
            $now = CarbonImmutable::now('Africa/Maputo');
            $days = (int) $date->startOfDay()->diffInDays($now->startOfDay(), true);
            $past = ! $date->isFuture();
            $human = $past ? ($days === 0 ? 'Terminou hoje' : ($days === 1 ? 'Terminou ontem' : 'Terminou há '.$days.' dias')) : ($days === 0 ? 'Termina hoje às '.$date->format('H:i') : ($days === 1 ? 'Termina amanhã às '.$date->format('H:i') : 'Termina em '.$days.' dias'));
            $deadline = ['value' => $date->toIso8601String(), 'label' => $human, 'detail' => $date->format('d/m/Y H:i').' · Maputo', 'isPast' => $past];
        }

        return ['code' => $code, 'label' => $label, 'tone' => in_array($code, ['paid', 'ready', 'completed'], true) ? 'success' : (in_array($code, ['payment_pending', 'issue', 'refund_pending'], true) ? 'warning' : 'neutral'), 'isFinal' => $final, 'primaryAction' => $action, 'steps' => $steps, 'deadline' => $deadline,
            'description' => $presentation['description'] ?? ($final ? 'Esta participação foi encerrada.' : 'Avisamos quando abrirem os pagamentos.'),
            'canEditInterest' => $order === null && $participation->status !== 'cancelada' && $campaign->status === 'ativa' && $campaign->purchase_phase === 'interest' && $campaign->deadline->isFuture(),
            'canReportIssue' => ($presentation['canReportIssue'] ?? false) && in_array($campaign->purchase_phase, ['supply', 'completed'], true)];
    }

    /** @return array<string,mixed> */
    public function consumer(Campaign $campaign, Participation $participation, ?PurchaseOrder $order): array
    {
        $editable = $campaign->status === 'ativa' && $campaign->purchase_phase === 'interest' && $campaign->deadline->isFuture();
        if ($order) {
            $presentation = $this->order($order);

            return ['nextStep' => $presentation, 'presentation' => $presentation, 'canIssueOrder' => false, 'canPay' => $presentation['canPay'],
                'paymentBlockedReason' => $presentation['blockedReason'], 'participationEditable' => false];
        }
        if ($campaign->hasFixedPurchaseTerms() && $participation->status !== 'cancelada') {
            $blocked = $this->paymentBlockedReason($campaign);
            $expired = ! $campaign->payment_deadline?->isFuture();
            $cancelled = $campaign->purchase_phase === 'cancelled';
            if ($blocked === null && (int) ($campaign->purchase_config['available_quantity'] ?? 0) - $campaign->held_quantity < $participation->quantity) {
                $blocked = 'A quantidade disponível não cobre o seu pedido. Não foi iniciado um pagamento.';
            }
            $canPay = $blocked === null;
            $presentation = [...$this->step($cancelled ? 'cancelled' : ($expired ? 'expired' : 'payment'), $cancelled ? 'Cancelado' : ($expired ? 'Prazo de pagamento terminado' : 'A pagar'), 'Sistema Junta', $blocked ?? 'Escolha como pagar e aceite as condições. A disponibilidade é verificada ao iniciar o pagamento.', $canPay ? 'pay' : null),
                'terminal' => $cancelled, 'canPay' => $canPay, 'blockedReason' => $cancelled ? null : $blocked,
                'showDeadline' => ! $cancelled && ! $expired, 'showMinimum' => false, 'poll' => $canPay, 'canReportIssue' => false];

            return ['nextStep' => $presentation, 'presentation' => $presentation, 'canIssueOrder' => $canPay, 'canPay' => $canPay, 'paymentBlockedReason' => $blocked, 'participationEditable' => false];
        }
        $step = match (true) {
            $participation->status === 'cancelada' => $this->step('cancelled_interest', 'Interesse cancelado', 'Consumidor', $editable ? 'Pode retomar o interesse, indicando a quantidade abaixo.' : 'O interesse fechou.'),
            $campaign->purchase_phase !== 'interest' => $this->step('waiting', 'Aguarda actualização da compra', 'Sistema Junta', 'O organizador ou a administração acompanha esta compra.'),
            default => match ($campaign->effectiveStage()) {
                'interest' => $this->step('interest', 'A reunir intenção de compra', 'Consumidores e organizador', 'A quantidade é uma intenção. Ainda não há cobrança nem garantia de stock.'),
                'evaluation' => $this->step('evaluation', 'A comparar propostas', 'Organizador', 'Aguarde a escolha de uma oferta.'),
                'selection' => $this->step('selection', 'Proposta escolhida', 'Organizador', 'O organizador está a preparar os pagamentos com as condições da proposta seleccionada.'),
                default => $this->step('waiting', 'Aguarda actualização da campanha', 'Organizador', 'Acompanhe as notificações da sua compra.'),
            },
        };

        return ['nextStep' => $step, 'presentation' => null, 'canIssueOrder' => false, 'canPay' => false, 'paymentBlockedReason' => null, 'participationEditable' => $editable];
    }

    /** @param array<string,int> $metrics
     * @return array<string,mixed>
     */
    public function organizer(Campaign $campaign, array $metrics): array
    {
        $stage = $campaign->effectiveStage();
        $offer = $campaign->proposals->firstWhere('status', 'aceite');
        $action = null;
        $waiting = null;
        $blocker = null;
        $description = '';
        $attention = false;
        $makeAction = fn (string $code, string $label, string $target = 'purchase'): array => compact('code', 'label', 'target');
        $labels = ['draft' => 'Rascunho', 'interest' => 'Interesse', 'evaluation' => 'Propostas', 'selection' => 'Proposta escolhida',
            'confirmed' => 'Proposta escolhida', 'payment' => 'Pagamentos', 'review' => 'Pagamentos', 'supply' => (($metrics['receivedUnits'] ?? 0) > 0 && (($metrics['waitingOrders'] ?? 0) > 0 || ($metrics['readyOrders'] ?? 0) > 0) ? 'Em distribuição' : 'Mercadoria a caminho'), 'completed' => 'Concluída', 'cancelled' => 'Cancelada'];
        if ($stage === 'draft') {
            $action = $makeAction('edit', 'Continuar preparação', 'edit');
            $description = 'Complete o produto e as condições para publicar a campanha.';
            $attention = true;
        } elseif ($stage === 'interest') {
            $expired = ! $campaign->deadline->isFuture();
            $action = $expired ? $makeAction('close_interest', 'Fechar interesse e avaliar propostas') : $makeAction('participants', 'Ver participantes', 'participants');
            $description = $expired ? 'O prazo terminou. Feche o interesse para escolher uma proposta.' : 'Acompanhe a procura. A quantidade registada ainda é uma intenção de compra.';
            $attention = $expired;
        } elseif ($stage === 'evaluation') {
            if (($metrics['openProposals'] ?? 0) > 0) {
                $action = $makeAction('proposals', 'Escolher fornecedor', 'proposals');
                $description = $metrics['openProposals'].' propostas para comparar. A decisão é sua.';
                $attention = true;
            } else {
                $waiting = ['label' => 'A aguardar propostas', 'since' => $campaign->updated_at->toIso8601String()];
                $description = 'Os fornecedores verificados podem enviar propostas enquanto esta etapa estiver aberta.';
            }
        } elseif (in_array($stage, ['selection', 'confirmed'], true)) {
            $conditions = $campaign->purchase_config ?? ($offer ? ProposalValidation::conditions($offer) : []);
            $blocker = match (true) {
                ! $offer => 'Não há uma proposta escolhida para esta campanha.',
                ! $offer->supplier->verified => 'A administração precisa de verificar o fornecedor escolhido.',
                ProposalValidation::conditionsBlocker($conditions) !== null => ProposalValidation::conditionsBlocker($conditions),
                ! $this->gateway->available() => 'O pagamento está indisponível. A administração precisa de verificar a configuração.',
                default => null,
            };
            if ($blocker === null) {
                $action = $makeAction('open_payments', 'Pedir pagamento aos participantes');
                $description = 'A proposta escolhida fixa as condições comerciais. Defina a comissão, o prazo e como receber os pedidos.';
                $attention = true;
            } else {
                $waiting = ['label' => str_contains($blocker, 'administração') ? 'A aguardar a administração' : 'Decisão do organizador necessária', 'since' => $offer?->updated_at->toIso8601String() ?? $campaign->updated_at->toIso8601String()];
                $description = 'Resolva a causa indicada antes de pedir pagamentos aos participantes.';
            }
        } elseif (in_array($stage, ['payment', 'review'], true)) {
            $expired = $campaign->payment_deadline !== null && ! $campaign->payment_deadline->isFuture();
            $pending = $metrics['pendingPayments'] ?? 0;
            if ($expired && $pending > 0) {
                $blocker = $pending.' '.($pending === 1 ? 'pagamento por confirmar impede' : 'pagamentos por confirmar impedem').' a verificação do mínimo.';
                $description = ($metrics['escalatedPayments'] ?? 0) > 0 ? 'A administração foi notificada. O sistema continua a confirmar os pagamentos.' : 'O sistema está a confirmar os pagamentos. Os pagamentos demorados são encaminhados à administração.';
                $waiting = ['label' => 'A aguardar confirmação dos pagamentos', 'since' => $campaign->payment_deadline->toIso8601String()];
            } elseif ($expired) {
                $action = $makeAction('apure', 'Verificar o mínimo');
                $description = 'O prazo terminou e a compra ainda não foi decidida. A verificação é automática; pode iniciá-la agora como contingência.';
                $attention = true;
            } else {
                $action = $makeAction('payments', 'Acompanhar pagamentos');
                $description = 'Os participantes podem pagar até ao prazo. O mínimo será verificado automaticamente depois.';
            }
        } elseif ($stage === 'supply') {
            if (($metrics['minimumWaitingQuantity'] ?? 0) > 0 && $metrics['minimumWaitingQuantity'] <= ($metrics['unallocatedUnits'] ?? 0)) {
                $action = $makeAction('orders', 'Separar pedidos');
                $description = 'Há mercadoria recebida disponível. Prepare cada pedido por inteiro.';
            } elseif (($metrics['readyOrders'] ?? 0) > 0) {
                $action = $makeAction('handoff', 'Entregar pedidos prontos');
                $description = 'Peça o código privado ao participante apenas quando entregar o pedido.';
            } elseif (($metrics['waitingOrders'] ?? 0) > 0) {
                $action = $makeAction('receive', 'Registar recepção da mercadoria');
                $description = 'Registe a quantidade total recebida para poder preparar os pedidos.';
            } elseif (($metrics['openIssues'] ?? 0) > 0) {
                $waiting = ['label' => 'A aguardar a administração', 'since' => $campaign->updated_at->toIso8601String()];
                $blocker = $metrics['openIssues'].' pedidos com um problema em análise.';
                $description = 'A administração precisa de resolver os problemas reportados antes de continuar a entrega desses pedidos.';
            } else {
                $waiting = ['label' => 'A aguardar a administração', 'since' => $campaign->updated_at->toIso8601String()];
                $description = 'Os pedidos foram entregues. A administração verifica os valores pendentes e conclui a compra.';
            }
            $attention = $action !== null;
        }
        $final = in_array($stage, ['completed', 'cancelled'], true);
        $tone = $final ? ($stage === 'completed' ? 'success' : 'neutral') : ($blocker !== null ? 'warning' : (in_array($stage, ['draft', 'interest', 'evaluation'], true) ? 'info' : 'success'));
        $icon = match ($stage) {
            'draft' => 'pencil', 'interest' => 'users', 'evaluation', 'selection', 'confirmed' => 'document', 'payment', 'review' => 'wallet', 'supply' => 'package', 'completed' => 'check', default => 'closed'
        };

        return ['code' => $stage, 'label' => $labels[$stage] ?? 'Em preparação', 'tone' => $tone, 'icon' => $icon, 'isFinal' => $final,
            'primaryAction' => $final ? null : $action, 'waitingOn' => $final ? null : $waiting, 'blocker' => $final ? null : $blocker,
            'description' => $description, 'attention' => ! $final && $attention,
            'step' => ['draft' => 0, 'interest' => 1, 'evaluation' => 2, 'selection' => 3, 'confirmed' => 3, 'payment' => 4, 'review' => 4, 'supply' => 5, 'completed' => 6, 'cancelled' => -1][$stage] ?? 0];
    }

    /** @param array<string,mixed> $state
     * @param  array<string,int>  $metrics
     * @return array<string,mixed>
     */
    public function organizerList(Campaign $campaign, array $state, array $metrics, ?string $deadline): array
    {
        $stage = $state['code'];
        $section = $state['isFinal'] ? 'closed' : ($stage === 'draft' ? 'drafts' : (($state['attention'] && ! ($stage === 'supply' && $metrics['receivedUnits'] === 0)) ? 'attention' : 'waiting'));
        $labels = ['close_interest' => 'Fechar interesse', 'proposals' => 'Escolher fornecedor', 'open_payments' => 'Pedir pagamentos', 'apure' => 'Verificar mínimo', 'orders' => 'Separar pedidos', 'handoff' => 'Entregar pedidos', 'receive' => 'Registar recepção'];
        $action = $section === 'attention' ? $state['primaryAction'] : null;
        if ($action !== null) {
            $action['label'] = $labels[$action['code']] ?? $action['label'];
        }
        $pending = $metrics['pendingPayments'];
        $waiting = match (true) {
            $section !== 'waiting' => null,
            $pending > 0 => $pending.' '.($pending === 1 ? 'pagamento por confirmar' : 'pagamentos por confirmar'),
            $stage === 'supply' && $metrics['receivedUnits'] === 0 => 'Mercadoria a caminho',
            in_array($stage, ['payment', 'review'], true) => 'Pagamentos a decorrer',
            $stage === 'interest' => 'Interesse a decorrer',
            $stage === 'evaluation' => 'Aguarda propostas',
            str_contains($state['waitingOn']['label'] ?? '', 'administração') => 'Aguarda a administração',
            in_array($stage, ['selection', 'confirmed'], true) => 'Decisão do organizador necessária',
            default => 'Aguarda actualização',
        };
        $measurement = match ($stage) {
            'payment', 'review' => 'Pagos', 'supply', 'completed' => 'Recebidas', default => 'Procura'
        };
        $value = match ($measurement) {
            'Pagos' => $metrics['paidUnits'], 'Recebidas' => $metrics['receivedUnits'], default => $metrics['interestUnits']
        };
        $target = match ($measurement) {
            'Pagos' => (int) ($campaign->purchase_config['minimum_quantity'] ?? 0), 'Recebidas' => $metrics['chargedUnits'], default => $campaign->target_quantity
        };
        $progress = $stage === 'cancelled' || $target < 1 ? null : ['value' => $value, 'target' => $target, 'label' => $measurement.': '.$value.' de '.$target.' '.$campaign->unit, 'heading' => $measurement === 'Procura' ? 'Procura do grupo' : ($measurement === 'Pagos' ? 'Pagos para o mínimo' : 'Mercadoria recebida')];
        $date = $deadline === null || in_array($stage, ['draft', 'supply', 'completed', 'cancelled'], true) ? null : CarbonImmutable::parse($deadline)->setTimezone('Africa/Maputo');
        $relative = null;
        if ($date !== null) {
            $now = CarbonImmutable::now('Africa/Maputo');
            $past = $date->lessThanOrEqualTo($now);
            $days = (int) $date->startOfDay()->diffInDays($now->startOfDay(), true);
            $label = $past ? ($days === 0 ? 'terminou hoje' : ($days === 1 ? 'terminou ontem' : 'terminou há '.$days.' dias')) : ($days === 0 ? 'termina hoje' : ($days === 1 ? 'termina amanhã' : 'termina em '.$days.' dias'));
            $relative = ['value' => $date->toIso8601String(), 'label' => $label, 'detail' => $date->format('d/m/Y').' às '.$date->format('H:i').' · Maputo', 'blocked' => $past && $section === 'waiting' && ($pending > 0 || $state['blocker'] !== null)];
        }

        return ['section' => $section, 'action' => $action, 'waiting' => $waiting, 'progress' => $progress, 'deadline' => $relative, 'priorityAt' => $date?->getTimestamp()];
    }

    /** @param array<string,mixed> $state
     * @param  array<string,mixed>  $list
     * @param  array<string,int>  $metrics
     * @return array<string,mixed>
     */
    public function organizerWorkspace(Campaign $campaign, array $state, array $list, array $metrics): array
    {
        $final = $state['isFinal'];
        $action = $final ? null : ($state['code'] === 'draft' ? $state['primaryAction'] : $list['action']);
        if ($action !== null) {
            $action['label'] = $state['primaryAction']['label'];
        }
        $waiting = ! $final && $action === null ? ['label' => $list['waiting'] ?? $state['waitingOn']['label'] ?? 'Interesse a decorrer', 'since' => $state['waitingOn']['since'] ?? $campaign->updated_at->toIso8601String()] : null;
        $plain = static fn (?string $text): string => str_ireplace(['prestador de teste', 'prestador', 'tentativas', 'tentativa', 'apuramento', 'conferência', 'reserva de capacidade', 'fornecimento', 'ocorrências', 'ocorrência'], ['sistema de pagamento', 'serviço de pagamento', 'pagamentos', 'pagamento', 'verificação do mínimo', 'verificação', 'disponibilidade', 'mercadoria a caminho', 'problemas com pedidos', 'problema com o pedido'], $text ?? '');
        $description = $plain($state['description']);
        $blocker = $state['blocker'] !== null ? $plain($state['blocker']) : null;
        if ($state['code'] === 'interest' && $action !== null) {
            $description = 'O prazo terminou; cabe a si fechar o interesse para comparar as propostas.';
        }
        if ($metrics['pendingPayments'] > 0 && $campaign->payment_deadline?->isPast()) {
            $blocker = $plain($state['blocker']).' '.($metrics['escalatedPayments'] > 0 ? 'O sistema e a administração estão a tratar.' : 'O sistema está a confirmar os pagamentos antes de verificar o mínimo.');
        }
        if ($metrics['escalatedPayments'] > 0 && $metrics['pendingPayments'] > 0) {
            $blocker = 'A administração foi notificada: '.$metrics['pendingPayments'].' '.($metrics['pendingPayments'] === 1 ? 'pagamento por confirmar impede' : 'pagamentos por confirmar impedem').' a verificação do mínimo. O sistema e a administração estão a tratar.';
        }
        $progress = ! $final && in_array($state['code'], ['interest', 'payment', 'review'], true) ? $list['progress'] : null;
        if ($progress !== null) {
            $progress['label'] = in_array($state['code'], ['payment', 'review'], true) ? 'Pagos '.$progress['value'].' de '.$progress['target'].' do mínimo' : $progress['value'].' de '.$progress['target'];
        }
        $deadline = ! $final && $list['deadline'] !== null ? [...$list['deadline'], 'isPast' => CarbonImmutable::parse($list['deadline']['value'])->lessThanOrEqualTo(CarbonImmutable::now())] : null;

        return ['code' => $state['code'], 'label' => $state['label'], 'tone' => $state['tone'], 'icon' => $state['icon'], 'isFinal' => $final, 'primaryAction' => $action, 'waitingOn' => $waiting, 'blocker' => $blocker, 'description' => $description, 'progress' => $progress, 'deadline' => $deadline, 'step' => $state['step'] + 1, 'responsible' => $action !== null ? 'Organizador' : ($metrics['escalatedPayments'] > 0 ? 'Sistema e administração' : ($waiting['label'] ?? 'Sem acção pendente'))];
    }

    /** @return array<string,mixed> */
    public function visibleParticipation(Participation $participation, Campaign $campaign, ?PurchaseOrder $order, int $available): array
    {
        if ($order !== null) {
            return $this->visibleOrder($order, $available);
        }
        $stage = $campaign->effectiveStage();
        $cancelled = $participation->status === 'cancelada' || $stage === 'cancelled';
        $final = $cancelled || $stage === 'completed';

        return ['code' => $cancelled ? 'cancelled' : ($final ? 'completed' : 'interest'),
            'label' => $cancelled ? 'Cancelado' : ($final ? 'Campanha concluída' : ($participation->status === 'confirmada' ? 'Interesse confirmado' : 'Interesse registado')),
            'tone' => $final ? 'neutral' : 'info', 'icon' => $final ? 'closed' : 'users', 'isFinal' => $final,
            'primaryAction' => null, 'waitingOn' => null, 'blocker' => null];
    }

    /** @return array<string,mixed> */
    public function visibleOrder(PurchaseOrder $order, int $available = 0): array
    {
        $current = $this->order($order, false);
        $code = $current['code'];
        $final = $current['terminal'] || in_array($order->campaign->effectiveStage(), ['completed', 'cancelled'], true);
        $blocked = $current['action'] === 'prepare' && $order->quantity > $available ? 'Este pedido precisa de '.$order->quantity.' unidades; há '.$available.' disponíveis. Registe a mercadoria em falta antes de separar.' : null;

        return ['code' => $code, 'label' => $current['title'], 'tone' => in_array($code, ['paid', 'ready', 'completed', 'refunded'], true) ? 'success' : (in_array($code, ['payment_pending', 'refund_pending', 'issue', 'expired'], true) ? 'warning' : 'neutral'),
            'icon' => in_array($code, ['paid', 'completed', 'refunded'], true) ? 'check' : ($code === 'ready' ? 'package' : ($code === 'cancelled' ? 'closed' : 'clock')),
            'isFinal' => $final, 'primaryAction' => ! $final && $current['action'] !== null ? ['code' => $current['action'], 'label' => $current['action'] === 'prepare' ? 'Separar' : 'Confirmar entrega', 'target' => 'purchase', 'disabled' => $blocked !== null] : null,
            'waitingOn' => null, 'blocker' => $blocked];
    }

    /** @return array<string,mixed> */
    public function administrativeOrder(PurchaseOrder $order): array
    {
        $current = $this->order($order, false);
        $final = $current['terminal'];
        $code = $current['code'];

        return ['code' => $code, 'label' => $current['title'], 'tone' => in_array($code, ['paid', 'ready', 'completed', 'refunded'], true) ? 'success' : (in_array($code, ['payment_pending', 'refund_pending', 'issue'], true) ? 'warning' : 'neutral'),
            'icon' => 'clock', 'isFinal' => $final, 'primaryAction' => null, 'waitingOn' => null, 'blocker' => null,
            'canVerify' => ! $final && ($order->status === 'refund_pending' || $order->attempts->contains(fn (PaymentAttempt $attempt): bool => in_array($attempt->status, ['pending', 'review'], true))),
            'canResolve' => ! $final && $order->issues->contains('status', 'open')];
    }

    /** @param array<string,mixed> $operations
     * @return array<string,mixed>
     */
    public function administrativeClosure(Campaign $campaign, array $operations): array
    {
        $entries = FinancialEntry::query()->where('campaign_id', $campaign->id);
        $supplierBalance = (int) (clone $entries)->where('kind', 'supplier_due')->sum('amount_cents') - (int) (clone $entries)->where('kind', 'supplier_settled')->sum('amount_cents');
        $carrierBalance = (int) (clone $entries)->where('kind', 'carrier_due')->sum('amount_cents') - (int) (clone $entries)->where('kind', 'carrier_settled')->sum('amount_cents');
        $orders = PurchaseOrder::query()->where('campaign_id', $campaign->id);
        $notReceived = (clone $orders)->where('status', 'paid')->where('receipt_status', '!=', 'received')->value('id');
        $refund = (clone $orders)->where('status', 'refund_pending')->value('id');
        $issueOrder = PurchaseIssue::query()->whereIn('purchase_order_id', (clone $orders)->select('id'))->where('status', 'open')->value('purchase_order_id');
        $target = fn (string $tab, ?int $orderId = null): string => route('admin.compras.index', array_filter(['campaignId' => $campaign->id, 'tab' => $tab, 'orderId' => $orderId], fn ($value): bool => $value !== null), absolute: false);

        return ['canComplete' => $operations['canComplete'], 'supplierBalanceCents' => $supplierBalance, 'carrierBalanceCents' => $carrierBalance, 'blockers' => $operations['completionBlockers'],
            'items' => [
                ['key' => 'received', 'label' => 'Pedidos pagos recebidos', 'complete' => $operations['notReceived'] === 0, 'href' => $target('pedidos', $notReceived)],
                ['key' => 'issues', 'label' => 'Sem problemas abertos', 'complete' => $operations['openIssues'] === 0, 'href' => $target('pedidos', $issueOrder)],
                ['key' => 'refunds', 'label' => 'Sem reembolsos pendentes', 'complete' => $operations['pendingRefunds'] === 0, 'href' => $target('pedidos', $refund)],
                ['key' => 'supplier', 'label' => 'Saldo do fornecedor a zero', 'complete' => $supplierBalance === 0, 'href' => $target('movimentos')],
                ['key' => 'carrier', 'label' => 'Saldo do transportador a zero', 'complete' => $carrierBalance === 0, 'href' => $target('movimentos')],
            ]];
    }

    /** @return array<string,mixed> */
    public function operations(Campaign $campaign): array
    {
        $orders = PurchaseOrder::query()->where('campaign_id', $campaign->id);
        $allocated = (int) (clone $orders)->whereIn('receipt_status', ['ready', 'received'])->sum('quantity');
        $supply = SupplyOrder::query()->where('campaign_id', $campaign->id)->first();
        $unallocated = max(0, ($supply->received_quantity ?? 0) - $allocated);
        $ready = (clone $orders)->where('status', 'paid')->where('receipt_status', 'ready')->count();
        $notReceived = (clone $orders)->where('status', 'paid')->where('receipt_status', '!=', 'received')->count();
        $preparable = (clone $orders)->where('status', 'paid')->where('receipt_status', 'waiting')->where('quantity', '<=', $unallocated)->count();
        $pending = PaymentAttempt::query()->whereIn('purchase_order_id', (clone $orders)->select('id'))->whereIn('status', ['pending', 'review'])->count();
        $refunds = (clone $orders)->where('status', 'refund_pending')->count();
        $issues = PurchaseIssue::query()->whereIn('purchase_order_id', (clone $orders)->select('id'))->where('status', 'open')->count();
        $entries = FinancialEntry::query()->where('campaign_id', $campaign->id);
        $balance = (int) (clone $entries)->whereIn('kind', ['supplier_due', 'carrier_due'])->sum('amount_cents') - (int) (clone $entries)->whereIn('kind', ['supplier_settled', 'carrier_settled'])->sum('amount_cents');
        $blockers = [];
        if ($campaign->purchase_phase !== 'supply') {
            $blockers[] = 'A compra ainda não está em fornecimento.';
        }
        if ($notReceived > 0) {
            $blockers[] = $notReceived.' pedidos pagos ainda não foram recebidos pelos consumidores.';
        }
        if ($issues > 0) {
            $blockers[] = $issues.' problemas com pedidos aguardam decisão administrativa.';
        }
        if ($refunds > 0) {
            $blockers[] = $refunds.' pedidos aguardam confirmação do reembolso.';
        }
        if ($balance !== 0) {
            $blockers[] = 'Falta conferir o saldo de fornecedor/transportador: '.Money::decimal($balance).' MZN.';
        }
        $offer = $campaign->proposals()->with('supplier')->where('status', 'aceite')->first();
        $conditions = $campaign->purchase_config ?? ($offer ? ProposalValidation::conditions($offer) : []);
        $openBlocked = match (true) {
            $campaign->status !== 'proposta_selecionada' || $campaign->purchase_phase !== 'interest' || $campaign->hasFixedPurchaseTerms() => 'A campanha tem de estar com proposta seleccionada e sem pagamentos fixados.',
            ! $offer => 'Não há uma proposta escolhida para esta campanha.',
            ! $offer->supplier->verified => 'A administração tem de verificar o fornecedor seleccionado.',
            ProposalValidation::conditionsBlocker($conditions) !== null => ProposalValidation::conditionsBlocker($conditions),
            ! $this->gateway->available() => 'A administração tem de disponibilizar o serviço de pagamento.',
            default => null,
        };
        $deadlinePassed = $campaign->payment_deadline !== null && ! $campaign->payment_deadline->isFuture();
        $escalated = PaymentAttempt::query()->whereIn('purchase_order_id', (clone $orders)->select('id'))->whereIn('status', ['pending', 'review'])->whereNotNull('escalated_at')->count();
        $canApure = $pending === 0 && in_array($campaign->purchase_phase, ['payment', 'review'], true) && $campaign->payment_deadline !== null && ! $campaign->payment_deadline->isFuture();
        $step = match ($campaign->effectiveStage()) {
            'interest' => $this->step('interest', 'Reunir interesse e fechar esta etapa', 'Organizador', 'Acompanhe as quantidades; feche o interesse para comparar propostas.', 'close_interest'),
            'evaluation' => $this->step('evaluation', 'Seleccionar uma proposta', 'Organizador', 'Compare ofertas. A IA é consultiva e não é necessária para seleccionar uma oferta elegível.', 'proposals'),
            'selection' => $this->step('selection', $openBlocked === null ? 'Abrir pagamentos' : 'Aguarda condições para abrir pagamentos', 'Organizador', $openBlocked ?? 'Confirme comissão, prazo dentro da reserva, ponto de recolha e entrega opcional.', $openBlocked === null ? 'open_payments' : null),
            'payment', 'review' => $this->step('payments', $deadlinePassed ? 'Prazo terminado' : 'Pagamentos abertos', 'Sistema Junta', $deadlinePassed ? ($pending > 0 ? $pending.' '.($pending === 1 ? 'pagamento por confirmar impede' : 'pagamentos por confirmar impedem').' a verificação do mínimo. O sistema continua a consultar o resultado.'.($escalated > 0 ? ' A administração foi notificada.' : ' Os pagamentos demorados serão encaminhados à administração.') : 'O sistema vai verificar o mínimo. Se a campanha continuar nesta fase, pode verificar agora como contingência.') : 'Os participantes podem pagar até ao prazo. O mínimo será verificado automaticamente depois.', $canApure ? 'apure' : null),
            'supply' => match (true) {
                $preparable > 0 => $this->step('prepare', 'Separar pedidos com a mercadoria recebida', 'Organizador', $unallocated.' unidades recebidas ainda não foram alocadas. Cada pedido é separado por inteiro.', 'orders'),
                $ready > 0 => $this->step('handoff', 'Confirmar recolha ou entrega', 'Consumidor e organizador', 'Os pedidos prontos aguardam o código privado apresentado pelo consumidor. Os restantes aguardam mercadoria suficiente.', 'orders'),
                $notReceived > 0 => $this->step('receive', 'Receber mercadoria do fornecedor', 'Fornecedor e organizador', 'Registe a quantidade acumulada recebida. Ainda não há mercadoria suficiente para separar os pedidos em espera.', 'receive'),
                default => $this->step('completion', 'Finalizar a compra', 'Administração', $blockers === [] ? 'As condições de conclusão estão cumpridas. A administração pode encerrar a compra.' : implode(' ', $blockers), 'admin'),
            },
            'cancelled' => $this->step('cancelled', $refunds > 0 ? 'Devoluções em processamento' : 'Compra cancelada', $refunds > 0 ? 'Administração' : 'Sem acção pendente', $refunds > 0 ? 'A administração acompanha as devoluções em processamento.' : 'Consulte o histórico e as devoluções confirmadas.', $refunds > 0 ? 'orders' : null),
            'completed' => $this->step('completed', 'Compra concluída', 'Sem acção pendente', 'O histórico e as condições foram preservados.'),
            default => $this->step('draft', 'Preparar e publicar a campanha', 'Organizador', 'Reveja a identificação e publique a campanha antes de reunir interesse.'),
        };

        return ['nextStep' => $step, 'canOpenPayments' => $openBlocked === null, 'openPaymentsBlockedReason' => $openBlocked,
            'deadlinePassed' => $deadlinePassed, 'escalatedPayments' => $escalated, 'canApure' => $canApure, 'canReceive' => $campaign->purchase_phase === 'supply' && $supply !== null,
            'unallocatedQuantity' => $unallocated, 'preparableCount' => $preparable, 'readyCount' => $ready,
            'pendingPayments' => $pending, 'pendingRefunds' => $refunds, 'openIssues' => $issues, 'notReceived' => $notReceived,
            'outstandingCents' => $balance, 'completionBlockers' => $blockers, 'canComplete' => $blockers === []];
    }
}
