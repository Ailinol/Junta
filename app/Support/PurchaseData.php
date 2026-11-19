<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use App\Models\User;

class PurchaseData
{
    public function __construct(private CollectivePurchase $purchases, private PaymentGateway $gateway, private PurchaseProgress $progress) {}

    /** @return array<string,mixed> */
    public function campaign(Campaign $campaign): array
    {
        $selected = $campaign->proposals()->where('status', 'aceite')->first()?->toArray();
        $conditions = $campaign->purchase_config ?? [];
        if ($selected !== null && $conditions !== []) {
            foreach (['minimum_quantity', 'available_quantity', 'transport_included', 'transport_unit_cents', 'delivery_days'] as $field) {
                if (isset($conditions[$field])) {
                    $selected[$field] = $conditions[$field];
                }
            }
            if (isset($conditions['unit_cents'])) {
                $selected['unit_price'] = Money::decimal((int) $conditions['unit_cents']);
            }
            if (isset($conditions['price_valid_until'])) {
                $selected['valid_until'] = $conditions['price_valid_until'];
            }
            if (isset($conditions['reserved_until'])) {
                $selected['reserved_until'] = $conditions['reserved_until'];
            }
        }

        return [
            'serverNow' => now()->toIso8601String(),
            'id' => $campaign->id, 'title' => $campaign->title, 'phase' => $campaign->purchase_phase,
            'status' => $campaign->status, 'effectiveStage' => $campaign->effectiveStage(), 'config' => $campaign->purchase_config,
            'paymentDeadline' => $campaign->payment_deadline?->toIso8601String(),
            'interestQuantity' => (int) $campaign->participations()->where('status', '!=', 'cancelada')->sum('quantity'),
            'targetQuantity' => $campaign->target_quantity,
            'paidQuantity' => (int) PurchaseOrder::query()->where('campaign_id', $campaign->id)->where('status', 'paid')->sum('quantity'),
            'heldQuantity' => $campaign->held_quantity, 'checkoutAvailable' => $this->gateway->available(),
            'availableMethods' => $this->gateway->availableMethods(),
            'testTools' => config('purchases.provider') === 'test' && $this->gateway->available(),
            'selectedProposal' => $selected,
            'supply' => SupplyOrder::query()->where('campaign_id', $campaign->id)->first()?->toArray(),
        ];
    }

    /** @return array<string,mixed> */
    public function consumer(Campaign $campaign, User $user): array
    {
        $participation = $campaign->participations()->where('user_id', $user->id)->first();
        abort_unless($participation !== null, 404);
        $order = PurchaseOrder::query()->where('participation_id', $participation->id)->where('user_id', $user->id)->first();
        $quote = $campaign->hasFixedPurchaseTerms() && $participation->status !== 'cancelada'
            ? $this->purchases->quote($campaign, $participation->quantity, 'recolha') : null;

        $order?->loadMissing('attempts');

        return [...$this->campaign($campaign), 'view' => $this->progress->consumerState($campaign, $participation, $order), 'participation' => ['id' => $participation->id, 'quantity' => $participation->quantity, 'notes' => $participation->notes], 'paymentContact' => $user->contact_preferences['phone'] ?? '', 'deliveryAddress' => $user->contact_preferences['address'] ?? '', 'paymentMethods' => array_values(array_filter($this->gateway->availableMethods(), fn (array $method): bool => $method['requiresPhone'])), 'confirmation' => $order ? $this->confirmation($order) : null, 'progress' => $this->progress->consumer($campaign, $participation, $order), 'order' => $order ? $this->order($order, true) : null, 'quote' => $quote,
            'deliveryQuote' => $quote && ! empty($campaign->purchase_config['delivery_zone']) ? $this->purchases->quote($campaign, $participation->quantity, 'entrega') : null];
    }

    /** @return list<array<string,mixed>> */
    public function consumerOrders(User $user): array
    {
        $orders = PurchaseOrder::query()->where('user_id', $user->id)->with(['campaign', 'attempts.refund', 'issues'])->get()->keyBy('participation_id');
        $platform = app(PlatformData::class);
        $records = Participation::query()->where('user_id', $user->id)->with('campaign.organizer')->latest('id')->get()->map(function (Participation $participation) use ($orders, $platform): array {
            $order = $orders->get($participation->id);

            return ['id' => $participation->id, 'campaign' => $platform->campaign($participation->campaign), 'quantity' => $order !== null ? $order->quantity : $participation->quantity,
                'orderId' => $order?->id, 'totalCents' => $order?->total_cents, 'pickupPoint' => $order?->conditions['pickup_point'] ?? null,
                'state' => $this->progress->consumerState($participation->campaign, $participation, $order)];
        });

        return array_values($records->sortBy(fn (array $record): array => [$record['state']['primaryAction'] === null ? 1 : 0, $record['state']['deadline']['value'] ?? '9999', -$record['id']])->values()->all());
    }

    /** @return array<string,mixed>|null */
    private function confirmation(PurchaseOrder $order): ?array
    {
        $attempt = $order->attempts->sortByDesc('id')->first();
        if (! $attempt) {
            return null;
        }
        $phone = $attempt->payment_phone;

        return ['status' => $attempt->status, 'method' => collect($this->gateway->availableMethods())->firstWhere('code', $attempt->payment_method)['label'] ?? 'pagamento',
            'phone' => $phone ? '+258 '.substr($phone, 4, 2).' *** '.substr($phone, -4) : null,
            'startedAt' => $attempt->created_at?->toIso8601String(), 'expiresAt' => $attempt->created_at?->copy()->addMinutes(3)->toIso8601String(),
            'canRetry' => $attempt->status === 'failed' && ! $order->attempts->contains(fn (PaymentAttempt $payment): bool => in_array($payment->status, ['pending', 'review'], true))];
    }

    /** @return array<string,mixed> */
    public function order(PurchaseOrder $order, bool $owner = false): array
    {
        $order->loadMissing(['campaign', 'user', 'attempts.refund', 'issues']);

        return [
            ...$order->attributesToArray(),
            'participantName' => $order->user->name,
            'presentation' => $this->progress->order($order, $owner),
            'collectionCode' => $owner && $order->status === 'paid' && $order->receipt_status === 'ready' ? $order->collection_code : null,
            'attempts' => $order->attempts->toArray(),
            'refunds' => $order->attempts->map(fn (PaymentAttempt $attempt) => $attempt->refund?->toArray())->filter()->values()->all(),
            'issues' => $order->issues->toArray(),
        ];
    }
}
