<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $decision,
        public ?string $note
    ) {
    }

    public function envelope(): Envelope
    {
        $subjectMap = [
            'approved' => 'Votre compte TalentLink a été activé !',
            'rejected' => 'Concernant votre inscription sur TalentLink',
            'revision_required' => 'Action requise : Révision de votre dossier TalentLink'
        ];

        return new Envelope(
            subject: $subjectMap[$this->decision] ?? 'Mise à jour de votre compte TalentLink',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification_decision',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
