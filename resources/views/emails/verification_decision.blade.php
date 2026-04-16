<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 16px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { color: #6366f1; font-size: 24px; font-weight: bold; }
        .content { margin-bottom: 30px; }
        .footer { font-size: 12px; color: #64748b; text-align: center; }
        .button { display: inline-block; padding: 12px 24px; background-color: #6366f1; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .note { background-color: #f8fafc; border-left: 4px solid #6366f1; padding: 15px; margin: 20px 0; font-style: italic; }
        .revision-box { background-color: #fffbeb; border: 1px solid #fcd34d; padding: 20px; border-radius: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">TalentLink</div>
        </div>

        <div class="content">
            <p>Bonjour {{ $user->nom }},</p>

            @if($decision === 'approved')
                <h2>Félicitations ! 🎉</h2>
                <p>Votre compte a été vérifié et activé par notre équipe. Vous pouvez maintenant accéder à toutes les fonctionnalités de la plateforme.</p>
                <p style="text-align: center;">
                    <a href="{{ config('app.frontend_url') }}/login" class="button">Se connecter</a>
                </p>
            @elseif($decision === 'rejected')
                <h2>Mise à jour concernant votre dossier</h2>
                <p>Après examen de vos documents, nous avons le regret de vous informer que votre demande d'inscription n'a pas pu être acceptée.</p>
                @if($note)
                    <div class="note">
                        <strong>Motif :</strong><br>
                        {{ $note }}
                    </div>
                @endif
                <p>Si vous pensez qu'il s'agit d'une erreur, vous pouvez nous contacter directement.</p>
            @elseif($decision === 'revision_required')
                <h2>Correction requise sur votre dossier 📝</h2>
                <p>Un administrateur a examiné votre document et demande une correction pour finaliser votre validation.</p>
                
                <div class="note">
                    <strong>Message de l'admin :</strong><br>
                    "{{ $note }}"
                </div>

                <div class="revision-box">
                    <strong>Étapes pour corriger votre dossier :</strong>
                    <ol>
                        <li>Connectez-vous à votre compte sur TalentLink.</li>
                        <li>Vous serez automatiquement redirigé vers la page de réexpédition.</li>
                        <li>Téléchargez un nouveau document lisible (PDF, PNG ou JPG).</li>
                        <li>Soumettez à nouveau votre dossier.</li>
                    </ol>
                </div>

                <p style="text-align: center; margin-top: 25px;">
                    <a href="{{ config('app.frontend_url') }}/login" class="button">Accéder à mon compte</a>
                </p>
            @endif

            <p>À bientôt,<br>L'équipe TalentLink</p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} TalentLink. Tous droits réservés.
        </div>
    </div>
</body>
</html>
