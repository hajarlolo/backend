<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class SystemNotificationService
{
    public function notifyAdminsPendingAccount(User $targetUser, string $targetType): void
    {
        $admins = User::query()->where('role', 'admin')->get(['id_user']);

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id_user,
                'type' => 'account_pending_review',
                'contenu' => json_encode([
                    'message' => sprintf(
                        'Nouveau compte %s en attente de validation: %s',
                        $targetType === 'student' ? 'etudiant' : 'entreprise',
                        $targetUser->email
                    ),
                    'target_user_id' => $targetUser->id_user,
                    'target_role' => $targetType,
                    'requested_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE),
                'read' => false,
            ]);
        }
    }

    public function notifyUserModerationResult(User $targetUser, string $decision, ?string $note = null): void
    {
        $isApproved = in_array($decision, ['approuve', 'approved'], true);

        Notification::create([
            'user_id' => $targetUser->id_user,
            'type' => 'account_moderation_result',
            'contenu' => json_encode([
                'message' => $isApproved
                    ? 'Votre compte a ete valide par l\'administration.'
                    : 'Votre compte a ete refuse par l\'administration.',
                'decision' => $decision,
                'note' => $note,
                'processed_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE),
            'read' => false,
        ]);
    }
}

