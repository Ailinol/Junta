<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\User;
use App\Notifications\PlatformActivity;
use Illuminate\Support\Facades\DB;

class PlatformNotifier
{
    public function send(User $user, string $key, string $title, string $message, string $link, ?string $preference = null): bool
    {
        return DB::transaction(function () use ($user, $key, $title, $message, $link, $preference): bool {
            User::query()->whereKey($user->id)->update(['updated_at' => DB::raw('updated_at')]);
            $recipient = User::query()->find($user->id);
            if (! $recipient || ($preference !== null && ! (bool) ($recipient->contact_preferences[$preference] ?? true))) {
                return false;
            }
            $eventHash = hash('sha256', $key);
            if (AuditEntry::query()->where('action', 'notification.sent')->where('subject_type', 'User')->where('subject_id', $recipient->id)->where('changes->eventHash', $eventHash)->exists()) {
                return false;
            }
            if ($recipient->notifications()->where('data->eventKey', $key)->exists()) {
                return false;
            }
            $recipient->notify(new PlatformActivity($title, $message, $link, $key));
            AuditEntry::create(['actor_name' => 'Sistema Junta', 'action' => 'notification.sent', 'subject_type' => 'User', 'subject_id' => $recipient->id, 'changes' => ['eventHash' => $eventHash]]);

            return true;
        }, 5);
    }
}
