<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserVerificationController extends Controller
{
    public function __construct(private readonly UserActivityLogService $userActivityLogService)
    {
    }

    /**
     * Verify email via code.
     * Step 3 - الجزء الأول
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $verification = $user->latestVerification;

        if (!$verification || $verification->verification_code !== $request->code) {
            return response()->json(['message' => 'Code de vérification invalide.'], 400);
        }

        if (Carbon::parse($verification->code_expires_at)->isPast()) {
            return response()->json(['message' => 'Le code a expiré.'], 400);
        }

        // Email verified
        $user->email_verified_at = now();
        $user->save();

        // Log action
        $this->userActivityLogService->log($user, 'update_profile', 'verification', $verification->id_verification, "Email verified via code.");

        return response()->json([
            'message' => 'Email vérifié avec succès. Veuillez maintenant télécharger votre document.',
            'status' => 'pending_document'
        ]);
    }

    /**
     * Combined Step 3: verify code AND upload document.
     */
    public function submitStep3(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'document' => 'required|file|mimes:pdf,jpg,png,jpeg|max:5120',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $verification = $user->latestVerification;

        if (!$verification || $verification->verification_code !== $request->code) {
            return response()->json(['message' => 'Code de vérification invalide.'], 400);
        }

        if (Carbon::parse($verification->code_expires_at)->isPast()) {
            return response()->json(['message' => 'Le code a expiré.'], 400);
        }

        try {
            $path = $request->file('document')->store('verification_documents', 'public');

            // Transaction to update user and verification
            DB::transaction(function () use ($user, $verification, $path) {
                $user->email_verified_at = now();
                $user->save();

                $verification->update([
                    'verification_document' => $path,
                    'verification_code' => null,
                    'code_expires_at' => null,
                    'status' => 'pending_document',
                    'status_note' => null,
                ]);

                // Log action
                $this->userActivityLogService->log($user, 'update_profile', 'verification', $verification->id_verification, "Step 3 completed: Email verified and document uploaded.");
            });

            return response()->json([
                'message' => 'Vérification en cours. Votre document a été soumis avec succès.',
                'status' => 'pending_document'
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting Step 3: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la soumission.'], 500);
        }
    }

    /**
     * Upload verification document (standalone).
     * Step 3 - الجزء الثاني
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'document' => 'required|file|mimes:pdf,jpg,png,jpeg|max:5120',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $verification = $user->latestVerification;

        if (!$verification) {
            return response()->json(['message' => 'Processus de vérification non entamé.'], 400);
        }

        try {
            $path = $request->file('document')->store('verification_documents', 'public');

            $verification->update([
                'verification_document' => $path,
                'verification_code' => null,
                'code_expires_at' => null,
                'status' => User::STATUS_PENDING_DOCUMENT,
                'status_note' => null,
            ]);

            // Log action
            $this->userActivityLogService->log($user, 'resubmit_document', 'verification', $verification->id_verification, "Verification document uploaded.");

            return response()->json([
                'message' => 'Document téléchargé avec succès. En attente de validation par l\'administrateur.',
                'status' => User::STATUS_PENDING_DOCUMENT
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading verification document: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du téléchargement du document.'], 500);
        }
    }

    /**
     * Get verification status.
     */
    public function getStatus(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user && $request->email) {
            $user = User::where('email', $request->email)->first();
        }

        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $verification = $user->latestVerification;

        return response()->json([
            'status' => $verification?->status ?? 'none',
            'is_active' => $user->is_active,
            'status_note' => $verification?->status_note,
            'email_verified' => $user->email_verified_at !== null,
        ]);
    }

    /**
     * Admin decision on verification.
     * Step 5
     */
    public function adminDecision(Request $request, $id_verification): JsonResponse
    {
        $request->validate([
            'decision' => 'required|in:approved,rejected,revision_required',
            'note' => 'required_if:decision,rejected,revision_required',
        ]);

        $statusMap = [
            'approved' => 'approved',
            'rejected' => 'rejected',
            'revision_required' => 'revision_required'
        ];

        $status = $statusMap[$request->decision];
        $verification = UserVerification::findOrFail($id_verification);
        $user = $verification->user;

        DB::transaction(function () use ($verification, $user, $request, $status) {
            $verification->update([
                'status' => $status,
                'status_note' => $request->note,
            ]);

            if ($status === 'approved') {
                $user->is_active = 1;
                $user->save();
                $action = 'accept_user';
            } else if ($status === 'rejected') {
                $user->is_active = 0;
                $user->save();
                $action = 'reject_user';
            } else {
                // revision_required
                $user->is_active = 0;
                $user->save();
                $action = 'request_revision';
            }

            // Log action
            $this->userActivityLogService->log(Auth::user(), $action, 'verification', $verification->id_verification, "Admin decision: {$status}. Note: {$request->note}");

            // Send notification email
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\VerificationDecisionMail($user, $status, $request->note));
            } catch (\Exception $e) {
                // Log but don't fail the transaction
                \Illuminate\Support\Facades\Log::warning("Could not send verification decision email to {$user->email}: " . $e->getMessage());
            }
        });

        return response()->json(['message' => 'Décision enregistrée avec succès.']);
    }
}
