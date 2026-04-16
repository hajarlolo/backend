<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Code de verification entreprise')
            ->line('Votre code de verification est : ' . $this->code)
            ->line('Ce code expire dans 15 minutes.')
            ->line('Si vous n\'etes pas a l\'origine de cette demande, ignorez ce message.');
    }
}

