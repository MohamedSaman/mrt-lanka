<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSaleNotification extends Notification
{
    use Queueable;

    public $sale;
    public $creatorName;

    /**
     * Create a new notification instance.
     */
    public function __construct($sale, $creatorName)
    {
        $this->sale = $sale;
        $this->creatorName = $creatorName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'id' => $this->sale->id,
            'title' => 'New Sale Created',
            'message' => 'Sale #' . ($this->sale->invoice_number ?? $this->sale->id) . ' created by ' . $this->creatorName,
            'amount' => $this->sale->final_total ?? 0,
            'type' => 'order',
            'timestamp' => now()
        ];
    }
}
