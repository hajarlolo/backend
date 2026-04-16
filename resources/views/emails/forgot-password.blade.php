<!DOCTYPE html>
<html>
<head>
    <title>Réinitialisation de votre mot de passe</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;">
        <h2 style="color: #6d28d9; text-align: center;">TalentLink - Sécurité</h2>
        <p>Bonjour,</p>
        <p>Vous avez demandé la réinitialisation de votre mot de passe. Voici votre code de sécurité personnel :</p>
        
        <div style="background-color: #f9f9f9; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;">
            <p style="margin: 5px 0;"><span style="font-size: 2em; font-weight: bold; color: #6d28d9; letter-spacing: 4px;">{{ $codes }}</span></p>
        </div>

        <p style="text-align: center; color: #666; font-style: italic;">
            Veuillez sélectionner ce code parmi les choix proposés sur le site TalentLink.
        </p>

        <p>Ce code expirera dans 15 minutes. Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.</p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 0.8em; color: #999; text-align: center;">L'équipe TalentLink</p>
    </div>
</body>
</html>
