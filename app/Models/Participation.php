<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ParticipationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property int $campaign_id
 * @property int|null $user_id
 * @property int $quantity
 * @property string $status
 * @property string|null $notes
 * @property CarbonInterface $created_at
 * @property-read User|null $user
 */
#[Fillable(['quantity', 'notes'])]
class Participation extends Model
{
    /** @use HasFactory<ParticipationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
