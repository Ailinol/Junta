<?php

namespace App\Console\Commands;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Support\PlatformNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessPlatform extends Command
{
    protected $signature = 'junta:process-platform';

    protected $description = 'Fecha o interesse vencido, expira propostas e envia lembretes internos sem duplicação.';

    public function handle(PlatformNotifier $notifier): int
    {
        foreach (Campaign::query()->where('status', 'ativa')->where('purchase_phase', 'interest')->whereNull('purchase_config')->where('deadline', '<=', now()->addDay())->lazyById(100) as $campaign) {
            DB::transaction(function () use ($campaign, $notifier): void {
                Campaign::query()->whereKey($campaign->id)->update(['held_quantity' => DB::raw('held_quantity')]);
                $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
                if ($locked->status !== 'ativa' || $locked->purchase_phase !== 'interest' || $locked->purchase_config !== null) {
                    return;
                }
                if (! $locked->deadline->isFuture()) {
                    $locked->status = 'em_avaliacao';
                    $locked->save();
                    AuditEntry::create(['actor_name' => 'Sistema Junta', 'action' => 'interest.closed.automatically', 'subject_type' => 'Campaign', 'subject_id' => $locked->id]);
                    if ($locked->organizer) {
                        $notifier->send($locked->organizer, 'interest.closed:'.$locked->id, 'Campanha em avaliação', 'O prazo de interesse de '.$locked->title.' terminou. Compare as propostas recebidas.', route('organizador.campanhas.propostas', ['id' => $locked->id], absolute: false));
                    }

                    return;
                }
                foreach ($locked->participations()->with('user')->where('status', '!=', 'cancelada')->get() as $participation) {
                    if ($participation->user) {
                        $notifier->send($participation->user, 'interest.reminder:'.$locked->id.':'.$locked->deadline->timestamp, 'Prazo de interesse a terminar', 'O prazo de '.$locked->title.' termina nas próximas 24 horas. Reveja a sua quantidade.', route('consumidor.acompanhamento.show', ['id' => $locked->id], absolute: false), 'notifReminder');
                    }
                }
            }, 5);
        }
        foreach (Proposal::query()->whereIn('status', ['submetida', 'em_avaliacao'])->where('valid_until', '<=', now())->lazyById(100) as $proposal) {
            DB::transaction(function () use ($proposal, $notifier): void {
                Campaign::query()->whereKey($proposal->campaign_id)->update(['held_quantity' => DB::raw('held_quantity')]);
                $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
                if (! in_array($locked->status, ['submetida', 'em_avaliacao'], true) || $locked->valid_until->isFuture()) {
                    return;
                }
                $locked->status = 'expirada';
                $locked->save();
                AuditEntry::create(['actor_name' => 'Sistema Junta', 'action' => 'proposal.expired', 'subject_type' => 'Proposal', 'subject_id' => $locked->id]);
                if ($locked->supplier->user) {
                    $notifier->send($locked->supplier->user, 'proposal.expired:'.$locked->id, 'Proposta expirada', 'A validade da sua proposta para '.$locked->campaign->title.' terminou.', route('fornecedor.propostas.index', absolute: false));
                }
            }, 5);
        }
        foreach (PurchaseOrder::query()->with(['user', 'campaign'])->where('status', 'awaiting_payment')->where('payment_deadline', '>', now())->where('payment_deadline', '<=', now()->addDay())->lazyById(100) as $order) {
            foreach ([24, 2] as $hours) {
                if ($order->payment_deadline->greaterThan(now()->addHours($hours)) || ($hours === 24 && ! $order->payment_deadline->greaterThan(now()->addHours(2)))) {
                    continue;
                }
                $notifier->send($order->user, 'payment.reminder:'.$order->id.':'.$order->payment_deadline->timestamp.':'.$hours,
                    'Pague a sua compra', 'O prazo para pagar '.$order->campaign->title.' termina nas próximas '.$hours.' horas.',
                    route('consumidor.acompanhamento.show', ['id' => $order->campaign_id], absolute: false), 'notifReminder');
            }
        }
        foreach (PurchaseOrder::query()->with('user')->where('status', 'paid')->where('receipt_status', 'ready')->lazyById(100) as $order) {
            $notifier->send($order->user, 'pickup.ready:'.$order->id, 'Pedido pronto para recolha ou entrega', 'O pedido '.$order->reference.' já está separado. Consulte o código e combine a entrega.', route('consumidor.acompanhamento.show', ['id' => $order->campaign_id], absolute: false), 'notifPickup');
        }
        $this->info('Prazos, propostas e lembretes internos processados.');

        return self::SUCCESS;
    }
}
