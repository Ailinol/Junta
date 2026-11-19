<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property positive-int $id
 * @property int|null $organizer_id
 * @property string $organizer_name
 * @property string $title
 * @property string $description
 * @property string $category
 * @property string $status
 * @property int $target_quantity
 * @property string $unit
 * @property numeric-string|null $estimated_price
 * @property CarbonInterface $deadline
 * @property string $location
 * @property string $delivery_mode
 * @property string|null $image_url
 * @property list<string>|null $tags
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property int|numeric-string|null $current_quantity
 * @property int|null $participant_count
 * @property-read User|null $organizer
 * @property string $purchase_phase
 * @property array<string,mixed>|null $purchase_config
 * @property CarbonInterface|null $payment_deadline
 * @property int $held_quantity
 */
#[Fillable(['organizer_id', 'organizer_name', 'title', 'description', 'category', 'status', 'target_quantity', 'unit', 'estimated_price', 'deadline', 'location', 'delivery_mode', 'image_url', 'tags'])]
class Campaign extends Model
{
    public const PUBLIC_STATUSES = ['ativa', 'em_avaliacao'];

    public function hasFixedPurchaseTerms(): bool
    {
        return $this->purchase_config !== null && ($this->purchase_phase !== 'interest' || array_key_exists('commission_bps', $this->purchase_config));
    }

    public function effectiveStage(): string
    {
        if (in_array($this->purchase_phase, ['payment', 'review', 'supply', 'completed', 'cancelled'], true)) {
            return $this->purchase_phase;
        }

        return match ($this->status) {
            'rascunho' => 'draft',
            'ativa' => 'interest',
            'em_avaliacao' => 'evaluation',
            'proposta_selecionada' => 'selection',
            'confirmada' => 'confirmed',
            'em_fornecimento' => 'supply',
            'concluida' => 'completed',
            'cancelada' => 'cancelled',
            default => $this->status,
        };
    }

    public function canReceiveProposals(): bool
    {
        return $this->purchase_phase === 'interest' && $this->purchase_config === null
            && ($this->status === 'em_avaliacao' || ($this->status === 'ativa' && $this->deadline->isFuture()));
    }

    public function canEditConditions(): bool
    {
        return $this->purchase_phase === 'interest' && $this->purchase_config === null
            && in_array($this->status, ['rascunho', 'ativa'], true)
            && ! ($this->hasAttribute('participations_exists') ? $this->getAttribute('participations_exists') : $this->participations()->exists())
            && ! ($this->hasAttribute('proposals_exists') ? $this->getAttribute('proposals_exists') : $this->proposals()->exists());
    }

    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['deadline' => 'datetime', 'tags' => 'array', 'estimated_price' => 'decimal:2', 'target_quantity' => 'integer', 'purchase_config' => 'array', 'payment_deadline' => 'datetime', 'held_quantity' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /** @return HasMany<Participation, $this> */
    public function participations(): HasMany
    {
        return $this->hasMany(Participation::class);
    }

    /** @return HasMany<Proposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
