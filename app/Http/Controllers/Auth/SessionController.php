<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class SessionController extends Controller
{
    public function __construct(private readonly \App\Services\UserActivityLogService $userActivityLogService)
    {
    }
    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe invalide.'],
            ]);
        }

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            (bool) ($credentials['remember'] ?? false)
        )) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe invalide.'],
            ]);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = $request->user();

        // Block login for users not active (except admin role)
        if (! $user->isAdmin() && ! $user->is_active) {
             $status = $user->latestVerification?->status;

             if ($status === User::STATUS_REJECTED) {
                 $errorMessage = 'Votre dossier a été refusé par l\'administration.';
             } elseif ($status === User::STATUS_REVISION_REQUIRED) {
                 // Allow login for revision_required users to resubmit documents
                 // But don't set them as active - they'll be redirected to resubmit page
                 return response()->json([
                     'user' => [
                         'id_user' => $user->id_user,
                         'nom' => $user->nom,
                         'email' => $user->email,
                         'role' => $user->role,
                         'is_active' => $user->is_active,
                         'email_verified_at' => $user->email_verified_at,
                         'created_at' => $user->created_at,
                         'updated_at' => $user->updated_at,
                     ],
                     'message' => 'Document à soumettre à nouveau. Redirection vers la page de soumission.',
                     'redirect_to' => '/resubmit-document',
                 ]);
             } elseif ($status === User::STATUS_PENDING_DOCUMENT || $status === User::STATUS_PENDING_EMAIL) {
                 $errorMessage = 'Votre compte est en attente de validation par l\'administration.';
             } elseif ($user->hasVerifiedEmail()) {
                 // If email is verified but not active, they are waiting for admin approval
                 $errorMessage = 'Votre compte n\'est pas encore validé par l\'administration.';
             } else {
                 // If email not verified, redirect to verification page
                 return response()->json([
                     'message' => 'Email non vérifié. Redirection vers la vérification.',
                     'redirect_to' => '/verification/step-3?email=' . urlencode($user->email),
                 ]);
             }

             if (isset($errorMessage)) {
                 Auth::guard('web')->logout();
                 $request->session()->invalidate();
                 $request->session()->regenerateToken();

                 throw ValidationException::withMessages([
                     'email' => [$errorMessage],
                 ]);
             }
        }

        // Logging the login action
        $this->userActivityLogService->log($user, 'login', 'user', $user->id_user, "User logged in: {$user->email}");

        return response()->json([
            'message' => 'Connexion reussie.',
            'redirect_to' => $this->resolveRedirectPath($user),
            'user' => [
                'id' => $user->id_user,
                'nom' => $user->nom,
                'email' => $user->email,
                'role' => $user->role,
                'verification_status' => $user->latestVerification?->status,
                'profile_completed' => $user->profile_completed,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user) {
            $this->userActivityLogService->log($user, 'logout', 'user', $user->id_user, "User logged out: {$user->email}");
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Deconnexion reussie.']);
    }

    public function verificationNotice(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Votre email doit etre verifie avant de continuer.',
            'resend_endpoint' => route('verification.send'),
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $rateLimitKey = null;

        if (! $user) {
            $validated = $request->validate([
                'email' => ['required', 'email'],
            ]);

            $user = User::query()->where('email', $validated['email'])->first();

            if (! $user) {
                // Generic response to avoid user enumeration.
                return response()->json(['message' => 'Si un compte existe, l\'email de verification a ete envoye.']);
            }

            $rateLimitKey = 'verification-email:email:' . sha1($validated['email']);
        }

        $rateLimitKey ??= 'verification-email:user:' . $user->id_user;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return response()->json([
                'message' => 'Un email vient d\'etre envoye. Merci de patienter avant de renvoyer le lien.',
            ], 429);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email deja verifie.']);
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            RateLimiter::clear($rateLimitKey);
            Log::error('Failed to resend verification email.', [
                'user_id' => $user->id_user,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Impossible d\'envoyer l\'email de verification pour le moment.',
            ], 503);
        }

        Log::info('Verification email resent.', [
            'user_id' => $user->id_user,
            'email' => $user->email,
        ]);

        return response()->json(['message' => 'Email de verification renvoye.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse|JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            Log::warning('Email verification failed - user not found', ['id' => $id]);
            
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Utilisateur introuvable.'], 404);
            }

            return redirect()->away($this->frontendUrl('/verify-email', [
                'status' => 'invalid',
            ]));
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            Log::warning('Email verification failed - invalid hash', [
                'user_id' => $user->id_user,
                'email' => $user->email,
            ]);
            
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Lien de verification invalide.'], 403);
            }

            return redirect()->away($this->frontendUrl('/verify-email', [
                'status' => 'invalid',
                'email' => $user->email,
            ]));
        }

        // Mark email as verified
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            Log::info('User email verified successfully', [
                'user_id' => $user->id_user,
                'email' => $user->email,
                'role' => $user->role,
            ]);
        }

        // For students, redirect to document upload
        if ($user->isStudent()) {
            $frontendRedirect = $this->frontendUrl('/student/onboarding/document-upload', [
                'upload_url' => URL::temporarySignedRoute(
                    'student.verification.upload.guest',
                    now()->addHours(24),
                    ['user' => $user->id_user],
                    false
                ),
            ]);
        } else {
            // For companies and others
            $frontendRedirect = $this->frontendUrl('/verify-email', [
                'status' => 'verified',
                'email' => $user->email,
                'role' => $user->role,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Email verifie avec succes.',
                'redirect_to' => $frontendRedirect,
            ]);
        }

        return redirect()->away($frontendRedirect);
    }

    private function frontendUrl(string $path, array $query = []): string
    {
        $base = rtrim((string) env('FRONTEND_URL', 'http://localhost:3001'), '/');
        $url = $base . '/' . ltrim($path, '/');

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    private function resolveRedirectPath(User $user): string
    {
        if ($user->isAdmin()) {
            return '/admin/dashboard';
        }

        $v = $user->latestVerification;
        $status = $v?->status;

        // Step 1: Check if Email is verified (for those who logic require it)
        if (! $user->hasVerifiedEmail()) {
            return '/verification/step-3?email=' . urlencode($user->email);
        }

        if ($status === User::STATUS_PENDING_DOCUMENT) {
            return '/verification/step-3?status=pending';
        }

        if ($status === User::STATUS_PENDING_EMAIL) {
            return '/verification/step-3';
        }

        // Step 3: Approved Case
        if ($user->isApprovedForAccess()) {
            if ($user->isStudent() || $user->isLaureat()) {
                return $user->profile_completed ? '/student/dashboard' : '/student/profile-setup';
            }
            if ($user->isCompany()) {
                return $user->profile_completed ? '/company/dashboard' : '/company/profile-setup';
            }
        }

        return '/login';
    }

    private function transitionAfterEmailVerification(User $user): void
    {
        $current = $user->normalizedVerificationStatus();
        
        // Don't change status if already approved or rejected
        if (in_array($current, [User::STATUS_APPROVED, User::STATUS_REJECTED], true)) {
            return;
        }

        // After email verification, keep status as 'email_pending'
        // It will change to 'en_attente' only after document upload
        if ($user->verification_status === 'email_pending') {
            // Just mark email as verified, don't change verification_status yet
            return;
        }
    }

    private function loginBlockedMessage(User $user): string
    {
        if (! $user->hasVerifiedEmail()) {
            return 'Veuillez verifier votre email avant de vous connecter.';
        }

        // Check if student has uploaded document
        if ($user->isStudent()) {
            $hasDocument = (bool) optional($user->etudiant)->document_verification;
            if (! $hasDocument) {
                return 'Email verifie. Veuillez televerser votre document etudiant pour continuer.';
            }
        }

        return match ($user->normalizedVerificationStatus()) {
            User::STATUS_PENDING_DOCUMENT => 'Votre compte est en cours de validation par l\'administrateur. Vous serez notifie une fois valide.',
            User::STATUS_REJECTED => 'Votre dossier a ete refuse. Merci de contacter l\'administration.',
            default => 'Votre compte n\'est pas encore autorise a se connecter.',
        };
    }
}

