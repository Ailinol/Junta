<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\OrganizerApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property int $user_id
 * @property string $full_name
 * @property string $phone
 * @property string $location
 * @property string $motivation
 * @property string $status
 * @property string|null $decision_reason
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property CarbonInterface $created_at
 * @property-read User $user
 * @property-read User|null $reviewer
 */
#[Fillable(['full_name', 'phone', 'location', 'motivation'])]
class OrganizerApplication extends Model
{
    /** @use HasFactory<OrganizerApplicationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
