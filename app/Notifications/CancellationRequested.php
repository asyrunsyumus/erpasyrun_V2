<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class CancellationRequested extends Notification
{
    public function __construct(public string $number, public int $orderId, public string $by) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'cancellation_requested',
            'message' => "Pengajuan pembatalan order {$this->number} oleh {$this->by}.",
            'href' => '/cancellations',
        ];
    }
}
