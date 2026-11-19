<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PlatformActivity extends Notification
{
    public function __construct(public string $title, public string $message, public string $link, public string $eventKey, public string $kind = 'info') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title:string,message:string,type:string,link:string,eventKey:string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'type' => $this->kind, 'link' => $this->link, 'eventKey' => $this->eventKey];
    }
}
