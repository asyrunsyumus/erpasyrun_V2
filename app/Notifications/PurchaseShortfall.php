<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Dikirim ke setiap Owner (per orang, prinsip P7) saat belanja Mesir kurang dari kebutuhan. */
class PurchaseShortfall extends Notification
{
    public function __construct(public string $title, public int $missing, public string $by) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'purchase_shortfall',
            'message' => "Belanja \"{$this->title}\" masih kurang {$this->missing} buah (dicatat {$this->by}).",
            'href' => '/purchases',
        ];
    }
}
