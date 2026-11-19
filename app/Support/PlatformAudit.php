<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PlatformAudit
{
    /** @param array<string,mixed> $changes */
    public static function record(User $actor, string $action, Model $subject, array $changes = []): void
    {
        AuditEntry::create(['user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->getKey(), 'changes' => $changes]);
    }
}
