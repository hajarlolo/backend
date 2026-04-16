<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCompanyRequest;
use App\Models\Entreprise;
use App\Models\User;
use App\Notifications\CompanyVerificationCodeNotification;
use App\Services\SystemNotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompanyRegistrationController extends Controller
{
    public function __construct(
        private readonly SystemNotificationService $systemNotificationService,
        private readonly \App\Services\UserActivityLogService $userActivityLogService
    ) {
    }

    public function store(RegisterCompanyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $verificationEmailSent = true;

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'nom' => $validated['nom_entreprise'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'company',
                'is_active' => false,
                'email_verified_at' => null,
            ]);

            // Create verification record (Step 2)
            \App\Models\UserVerification::create([
                'id_user' => $user->id_user,
                'verification_code' => str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'code_expires_at' => now()->addMinutes(10),
                'status' => User::STATUS_PENDING_EMAIL,
            ]);

            Entreprise::create([
                'id_user' => $user->id_user,
                'nom_entreprise' => $validated['nom_entreprise'],
                'ice' => $validated['ice'] ?? null,
                'date_inscription' => now(),
            ]);

            // notifyAdmins will happen after they submit Step 3 usually
            // $this->systemNotificationService->notifyAdminsPendingAccount($user, 'company');

            return $user;
        });

        // Logging the registration action
        $this->userActivityLogService->log($user, 'register', 'user', $user->id_user, "Company registered: {$user->email}");

        try {
            Log::info('Attempting to send company verification email', [
                'user_id' => $user->id_user,
                'email' => $user->email,
            ]);

            $user->sendEmailVerificationNotification();
            
            $verificationEmailSent = true;
            
            Log::info('Company verification email sent successfully', [
                'user_id' => $user->id_user,
                'email' => $user->email,
            ]);
        } catch (Throwable $exception) {
            $verificationEmailSent = false;

            Log::error('Failed to send company verification email after registration.', [
                'user_id' => $user->id_user,
                'email' => $user->email,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        Log::info('Company registration completed.', [
            'user_id' => $user->id_user,
            'email' => $user->email,
            'verification_email_sent' => $verificationEmailSent,
        ]);

        return response()->json([
            'message' => $verificationEmailSent
                ? 'Inscription entreprise enregistree. Verifiez votre email pour activer votre compte.'
                : 'Inscription entreprise enregistree, mais l\'email de verification n\'a pas pu etre envoye.',
            'profile_completion_endpoint' => route('company.profile.complete'),
            'user_id' => $user->id_user,
            'verification_email_sent' => $verificationEmailSent,
        ], 201);
    }
}