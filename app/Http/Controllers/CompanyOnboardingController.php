<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteCompanyProfileRequest;
use App\Http\Requests\VerifyCompanyEmailCodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyOnboardingController extends Controller
{
    public function verifyEmailCode(VerifyCompanyEmailCodeRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('role', 'company')
            ->find($request->integer('user_id'));

        if (! $user) {
            return response()->json([
                'message' => 'Compte entreprise introuvable.',
            ], 404);
        }

        $company = $user->entreprise;
        if (! $company || ! $company->verification_code) {
            return response()->json([
                'message' => 'Aucun code valide trouve. Merci de redemander un code.',
            ], 422);
        }

        if ($company->verification_code_expires_at && $company->verification_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'Code expire. Merci de redemander un code.',
            ], 422);
        }

        if ($company->verification_code !== $request->string('code')->toString()) {
            return response()->json([
                'message' => 'Code invalide.',
            ], 422);
        }

        $company->update([
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'email_code_verified_at' => now(),
        ]);

        if ($user->hasVerifiedEmail()) {
            $user->update([
                'verification_status' => User::STATUS_PENDING,
                'status_updated_at' => now(),
                'is_active' => false,
            ]);
        }

        return response()->json([
            'message' => 'Email professionnel verifie. Le compte reste en attente de validation admin.',
        ]);
    }

    public function profileEdit(): JsonResponse
    {
        return response()->json([
            'message' => 'Completer le profil entreprise.',
            'endpoint' => route('company.profile.complete'),
        ]);
    }

    public function completeProfile(CompleteCompanyProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $company = $user->entreprise;

        if (! $company) {
            return response()->json(['message' => 'Profil entreprise introuvable.'], 404);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $company, $user): void {
            $logoPath = $company->logo_url;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('profiles/companies/' . $user->id_user, 'public');
            }

            $company->update([
                'description' => $validated['description'],
                'secteur_activite' => $validated['secteur_activite'],
                'site_web' => $validated['site_web'],
                'taille' => $validated['taille'],
                'telephone' => $validated['telephone'],
                'localisation' => $validated['localisation'],
                'email_professionnel' => $validated['email_professionnel'],
                'logo_url' => $logoPath,
                'profile_completed_at' => now(),
            ]);

            $user->update([
                'profile_completed' => true,
            ]);
        });

        return response()->json([
            'message' => 'Profil entreprise complete.',
            'redirect_to' => '/company/dashboard',
        ]);
    }
}
