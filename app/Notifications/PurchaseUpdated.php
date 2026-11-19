<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PurchaseUpdated extends Notification
{
    public function __construct(public int $campaignId, public string $message) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title:string,message:string,type:string,link:string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => 'Compra colectiva', 'message' => $this->message, 'type' => 'info', 'link' => route('consumidor.acompanhamento.show', ['id' => $this->campaignId], absolute: false)];
    }
}
