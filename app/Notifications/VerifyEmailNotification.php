<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Email verification notification - sends IMMEDIATELY (not queued)
 */
class VerifyEmailNotification extends Notification
{
    // NO Queueable trait - sends synchronously

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        Log::info('VerifyEmailNotification::toMail called', [
            'user_id' => $notifiable->getKey(),
            'email' => $notifiable->email,
            'url' => $verificationUrl,
        ]);

        return (new MailMessage())
            ->subject('Verification de votre adresse email - TalentLink')
            ->greeting('Bonjour ' . ($notifiable->prenom ?? 'utilisateur') . ',')
            ->line('Merci pour votre inscription sur TalentLink.')
            ->line('Cliquez sur le bouton ci-dessous pour verifier votre adresse email.')
            ->action('Verifier mon email', $verificationUrl)
            ->line('Ce lien expire dans 60 minutes.')
            ->line('Si vous n\'avez pas cree de compte, ignorez cet email.')
            ->salutation('L\'equipe TalentLink');
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
