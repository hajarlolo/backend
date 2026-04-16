<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ShowVerificationCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-code {email? : L\'email de l\'utilisateur} {--regenerate : Forcer la generation d\'un nouveau code}';

    protected $description = 'Affiche ou regenere le code de verification pour un etudiant ou une entreprise';

    public function handle()
    {
        $email = $this->argument('email');

        if (! $email) {
            $this->info("Merci de specifier un email : php artisan app:verify-code user@example.com");
            return 0;
        }

        $user = \App\Models\User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            $this->error("Aucun compte trouve pour l'email: {$email}");
            return 1;
        }

        $profile = $user->isStudent() ? $user->etudiant : ($user->isCompany() ? $user->entreprise : null);

        if (! $profile) {
            $this->error("Le profil associe n'existe pas ou le role n'utilise pas de code de verification.");
            return 1;
        }

        if ($this->option('regenerate') || ! $profile->verification_code || $profile->verification_code_expires_at?->isPast()) {
            $code = (string) random_int(100000, 999999);
            $expiresAt = now()->addMinutes(15);

            $profile->update([
                'verification_code' => $code,
                'verification_code_expires_at' => $expiresAt,
            ]);

            $this->info("Nouveau code genere avec succes!");
        }

        $this->info("Role: {$user->role}");
        $this->info("Email: {$user->email}");
        $this->info("Code: {$profile->verification_code}");
        $this->info("Expiration: " . ($profile->verification_code_expires_at?->toDateTimeString() ?? 'N/A'));

        return 0;
    }
}