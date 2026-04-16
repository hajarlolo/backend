<?php

namespace App\Services;

use App\Models\UserLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserActivityLogService
{
    /**
     * Log a user action.
     *
     * @param User $user The user performing the action
     * @param string $actionType Action type from defined enum
     * @param string|null $targetType Target type (user, offer, application)
     * @param int|null $targetId Target ID
     * @param string|null $description Optional description
     * @return UserLog
     */
    public function log(User $user, string $actionType, ?string $targetType = null, ?int $targetId = null, ?string $description = null): UserLog
    {
        return UserLog::create([
            'user_id' => $user->id_user,
            'user_type' => $this->mapRoleToUserType($user->role),
            'action_type' => $actionType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    /**
     * Map user role to the ENUM used in user_logs.
     */
    private function mapRoleToUserType(string $role): string
    {
        return match ($role) {
            'admin' => 'admin',
            'student' => 'etudiant',
            'lauriat' => 'lauriat',
            'company' => 'entreprise',
            default => 'etudiant', // fallback
        };
    }
}
