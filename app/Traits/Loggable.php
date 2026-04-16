<?php

namespace App\Traits;

use App\Services\UserActivityLogService;
use App\Models\User;
use Illuminate\Support\Facades\App;

trait Loggable
{
    /**
     * Log a user activity.
     */
    protected function logActivity(string $actionType, ?string $targetType = null, ?int $targetId = null, ?string $description = null): void
    {
        $logService = App::make(UserActivityLogService::class);
        $user = auth()->user();

        if ($user instanceof User) {
            $logService->log($user, $actionType, $targetType, $targetId, $description);
        }
    }
}
