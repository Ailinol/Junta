<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AuditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property string $actor_name
 * @property string $action
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string,mixed>|null $changes
 * @property CarbonInterface $created_at
 */
#[Fillable(['user_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'changes'])]
class AuditEntry extends Model
{
    /** @use HasFactory<AuditEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
