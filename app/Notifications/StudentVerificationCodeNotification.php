<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentVerificationCodeNotification extends Notification
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
            ->subject('Votre code de verification TalentLink')
            ->greeting('Bonjour ' . ($notifiable->prenom ?? '') . ',')
            ->line('Merci de vous etre inscrit. Voici votre code de verification :')
            ->line('**' . $this->code . '**')
            ->line('Ce code expire dans 15 minutes.')
            ->line('Si vous n\'etes pas a l\'origine de cette demande, ignorez ce message.')
            ->salutation('L\'equipe TalentLink');
    }
}
