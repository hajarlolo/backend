<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStudentRequest;
use App\Models\Candidat as Etudiant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StudentRegistrationController extends Controller
{
    public function __construct(private readonly \App\Services\UserActivityLogService $userActivityLogService)
    {
    }

    public function store(RegisterStudentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Create user in transaction
        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'nom' => $validated['nom'],
                // prenom removed to match user spec
                'email' => $validated['email'],
                'password' => $validated['password'], // Laravel hashes this if defined in model casts
                'role' => $validated['role'] ?? 'student',
                'is_active' => false,
                'email_verified_at' => null,
            ]);

            Etudiant::create([
                'id_user' => $user->id_user,
                'universite_id' => $validated['universite_id'],
            ]);

            // Create verification record (Step 2)
            \App\Models\UserVerification::create([
                'id_user' => $user->id_user,
                'verification_code' => str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'code_expires_at' => now()->addMinutes(10),
                'status' => User::STATUS_PENDING_EMAIL,
            ]);

            return $user;
        });

        // Logging the registration action
        $this->userActivityLogService->log($user, 'register', 'user', $user->id_user, "Student registered: {$user->email}");

        // Send verification email - SYNCHRONOUSLY
        try {
            Log::info('Attempting to send verification email', [
                'user_id' => $user->id_user,
                'email' => $user->email,
                'mail_host' => config('mail.mailers.smtp.host'),
                'mail_port' => config('mail.mailers.smtp.port'),
            ]);

            $user->sendEmailVerificationNotification();

            Log::info('Verification email sent successfully', [
                'user_id' => $user->id_user,
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('FAILED to send verification email', [
                'user_id' => $user->id_user,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Don't fail registration, just log the error
        }

        return response()->json([
            'message' => 'Inscription réussie. Un code de vérification a été envoyé à votre adresse email.',
            'email' => $user->email,
            'user_id' => $user->id_user,
            'verify_code_endpoint' => '/verification/submit-step3',
        ], 201);
    }
}

