<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification
{
    use Queueable;

    public $payment;
    public $type; // 'received' or 'paid'

    /**
     * Create a new notification instance.
     */
    public function __construct($payment, $type = 'received')
    {
        $this->payment = $payment;
        $this->type = $type;
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
        $title = $this->type === 'received' ? 'Payment Received' : 'Payment Made';
        $message = '';
        if ($this->type === 'received') {
            // Assuming $payment has amount and customer name
            $message = "Payment of Rs. " . number_format($this->payment->amount, 2) . " received.";
        } else {
            $message = "Payment of Rs. " . number_format($this->payment->amount, 2) . " paid.";
        }

        return [
            'id' => $this->payment->id,
            'title' => $title,
            'message' => $message,
            'amount' => $this->payment->amount ?? 0,
            'type' => 'payment',
            'timestamp' => now()
        ];
    }
}
