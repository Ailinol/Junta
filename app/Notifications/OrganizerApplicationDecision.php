<?php

namespace App\Notifications;

use App\Models\OrganizerApplication;
use Illuminate\Notifications\Notification;

class OrganizerApplicationDecision extends Notification
{
    public function __construct(public OrganizerApplication $application) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title:string,message:string,type:string,link:string} */
    public function toArray(object $notifiable): array
    {
        $approved = $this->application->status === 'approved';

        return ['title' => $approved ? 'Candidatura a organizador aprovada' : 'Decisão sobre a candidatura', 'message' => $approved ? 'A sua conta mantém o acesso de consumidor e passa também a organizar campanhas.' : ('A candidatura foi rejeitada. '.$this->application->decision_reason), 'type' => $approved ? 'success' : 'warning', 'link' => route('consumidor.candidatura', absolute: false)];
    }
}
