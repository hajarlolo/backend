<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteStudentProfileCvRequest;
use App\Http\Requests\CompleteStudentProfileManualRequest;
use App\Http\Requests\StudentVerificationDocumentRequest;
use App\Http\Requests\VerifyStudentEmailCodeRequest;
use App\Models\Candidat as Etudiant;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class StudentOnboardingController extends Controller
{
    public function __construct(
        private readonly SystemNotificationService $systemNotificationService,
        private readonly \App\Services\UserActivityLogService $userActivityLogService
    ) {
    }

    /**
     * Verify email with 6-digit code for students
     */
    public function verifyEmailCode(VerifyStudentEmailCodeRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('role', 'student')
            ->find($request->integer('user_id'));

        if (! $user) {
            return response()->json([
                'message' => 'Compte etudiant introuvable.',
            ], 404);
        }

        /** @var Etudiant|null $student */
        $student = $user->etudiant;
        if (! $student || ! $student->verification_code) {
            return response()->json([
                'message' => 'Aucun code valide trouve. Merci de redemander un code.',
            ], 422);
        }

        if ($student->verification_code_expires_at && $student->verification_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'Code expire. Merci de redemander un code.',
            ], 422);
        }

        if ($student->verification_code !== $request->string('code')->toString()) {
            return response()->json([
                'message' => 'Code invalide.',
            ], 422);
        }

        // Mark email as verified
        $student->update([
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // AUTO-LOGIN after verification to allow document upload
        \Illuminate\Support\Facades\Auth::login($user);

        Log::info('Student email verified and logged in with code', [
            'user_id' => $user->id_user,
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Email verifie avec succes.',
            'has_document' => (bool) $student->document_verification,
        ]);
    }

    /**
     * Get verification requirements for current student
     */
    public function verificationRequirements(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'email_verified' => $user->hasVerifiedEmail(),
            'document_uploaded' => (bool) optional($user->etudiant)->document_verification,
            'upload_endpoint' => route('student.verification.upload'),
            'accepted_formats' => ['png', 'jpg', 'jpeg', 'pdf'],
        ]);
    }

    /**
     * Upload verification document (authenticated route)
     */
    public function uploadVerificationDocument(StudentVerificationDocumentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->processDocumentUpload($user, $request);
    }

    /**
     * Upload verification document (guest route with signed URL)
     * Called after email verification when user is not logged in
     */
    public function uploadVerificationDocumentGuest(StudentVerificationDocumentRequest $request, User $user): JsonResponse
    {
        // Signature is already verified by 'signed' middleware
        
        if (! $user->isStudent()) {
            return response()->json(['message' => 'Compte etudiant introuvable.'], 404);
        }

        return $this->processDocumentUpload($user, $request);
    }

    /**
     * Process the document upload for a student
     */
    private function processDocumentUpload(User $user, StudentVerificationDocumentRequest $request): JsonResponse
    {
        Log::info('Document upload started', [
            'user_id' => $user->id_user,
            'email' => $user->email,
            'has_file' => $request->hasFile('verification_document'),
        ]);

        // Validate email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Veuillez verifier votre email avant de soumettre votre document.',
            ], 422);
        }

        // Check if already approved
        if ($user->isApprovedForAccess()) {
            return response()->json([
                'message' => 'Ce compte est deja valide.',
            ]);
        }

        // Get student profile
        /** @var Etudiant|null $student */
        $student = $user->etudiant;
        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        // Validate file exists
        if (! $request->hasFile('verification_document')) {
            return response()->json(['message' => 'Aucun fichier recu.'], 422);
        }

        $file = $request->file('verification_document');
        if (! $file->isValid()) {
            return response()->json(['message' => 'Le fichier est invalide ou corrompu.'], 422);
        }

        try {
            // Store file in public storage
            $path = $file->store(
                'documents/verification/' . $user->id_user,
                'public'
            );

            if (! $path) {
                throw new \RuntimeException('File storage returned empty path');
            }

            // Update student record
            $student->update([
                'document_verification' => $path,
            ]);

            // Update user status to pending_document (pending admin review)
            $user->update([
                'is_active' => false,
            ]);

            $user->latestVerification?->update([
                'status' => User::STATUS_PENDING_DOCUMENT,
                'verification_document' => $path,
            ]);

            // Notify admins
            $this->systemNotificationService->notifyAdminsPendingAccount($user, 'student');

            // Logging the registration action (includes document upload)
            $this->userActivityLogService->log($user, 'register', 'user', $user->id_user, "Student uploaded verification document: {$path}");

            Log::info('Document uploaded successfully', [
                'user_id' => $user->id_user,
                'path' => $path,
                'status' => $user->verification_status,
            ]);

            return response()->json([
                'message' => 'Votre document a ete envoye avec succes. Votre compte est maintenant en attente de validation par l\'administrateur.',
            ]);

        } catch (\Throwable $e) {
            Log::error('Document upload failed', [
                'user_id' => $user->id_user,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue pendant l\'envoi. Merci de reessayer.',
            ], 500);
        }
    }

    public function getProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        /** @var Etudiant|null $student */
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $student->load(['user']);

        $competences = DB::table('competence_candidat')
            ->join('competences', 'competence_candidat.id_competence', '=', 'competences.id_competence')
            ->where('id_candidat', $student->id_candidat)
            ->pluck('competences.nom');

        $formations = DB::table('formations')
            ->where('id_candidat', $student->id_candidat)
            ->get();
        
        $experiences = DB::table('experiences')
            ->where('id_candidat', $student->id_candidat)
            ->get()
            ->map(function($exp) {
                $skills = DB::table('competence_experience')
                    ->join('competences', 'competence_experience.id_competence', '=', 'competences.id_competence')
                    ->where('id_experience', $exp->id_experience)
                    ->pluck('competences.nom');

                return [
                    'id' => $exp->id_experience,
                    'type' => $exp->type,
                    'titre' => $exp->titre,
                    'nom_entreprise' => $exp->entreprise_nom,
                    'description' => $exp->description,
                    'date_debut' => $exp->date_debut,
                    'date_fin' => $exp->date_fin,
                    'competences' => $skills,
                ];
            });

        $projets = DB::table('projets')
            ->where('id_candidat', $student->id_candidat)
            ->get()
            ->map(function($p) {
                $techs = DB::table('projet_technologie')
                    ->join('competences', 'projet_technologie.id_competence', '=', 'competences.id_competence')
                    ->where('id_projet', $p->id_projet)
                    ->pluck('competences.nom');
                    
                return [
                    ... (array) $p,
                    'technologies' => $techs,
                    'image_apercu_url' => $p->image_apercu ? Storage::url($p->image_apercu) : null,
                ];
            });
        
        $certificats = DB::table('certificats')->where('id_candidat', $student->id_candidat)->get();

        $universityName = config("universities.{$student->universite_id}");

        return response()->json([
            'personal_info' => [
                'id_candidat' => $student->id_candidat,
                'nom' => $user->nom,
                'email' => $user->email,
                'telephone' => $student->telephone,
                'adresse' => $student->adresse,
                'date_naissance' => $student->date_naissance ? Carbon::parse($student->date_naissance)->format('Y-m-d') : null,
                'universite_id' => $student->universite_id,
                'universite_label' => $universityName,
                'academic_year' => $student->academic_year,
                'photo_profil' => $student->photo_profil ? Storage::url($student->photo_profil) : null,
                'lien_portfolio' => $student->lien_portfolio,
                'cv_url' => $student->cv_url ? Storage::url($student->cv_url) : null,
            ],
            'competences' => $competences,
            'formations' => $formations,
            'experiences' => $experiences,
            'projets' => $projets,
            'certificats' => $certificats,
        ]);
    }

    public function searchCompetences(Request $request): JsonResponse
    {
        $query = $request->input('q');
        
        $competencesQuery = DB::table('competences')->select('id_competence as id', 'nom as name');
        
        if ($query) {
            $competencesQuery->where('nom', 'like', '%' . $query . '%');
        }
        
        $competences = $competencesQuery->orderBy('nom')->limit(20)->get();

        return response()->json($competences);
    }

    public function updatePersonalInfo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Etudiant|null $student */
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            
            'telephone' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'universite_id' => ['required', 'integer'],
            'academic_year' => ['nullable', 'string', 'max:25'],
            'lien_portfolio' => ['nullable', 'url', 'max:255'],
            'photo_profil' => ['nullable', 'image', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $validated, $user, $student) {
            $user->update([
                'nom' => $validated['nom'],
                
            ]);

            $updateData = [
                'telephone' => $validated['telephone'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'date_naissance' => $validated['date_naissance'] ?? null,
                'universite_id' => $validated['universite_id'],
                'academic_year' => $validated['academic_year'] ?? null,
                'lien_portfolio' => $validated['lien_portfolio'] ?? null,
            ];

            if ($request->hasFile('photo_profil')) {
                $updateData['photo_profil'] = $request->file('photo_profil')
                    ->store('profiles/students/' . $user->id_user, 'public');
            }

            $student->update($updateData);
        });

        return response()->json(['message' => 'Informations personnelles mises à jour.']);
    }

    public function updateCompetences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $validated = $request->validate([
            'competences' => ['required', 'array'],
            'competences.*' => ['string', 'max:50'],
        ]);

        $this->syncCompetences($student->id_candidat, $validated['competences']);

        return response()->json(['message' => 'Compétences mises à jour.']);
    }

    public function updateFormations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $validated = $request->validate([
            'formations' => ['present', 'array'],
            'formations.*.diplome' => ['required', 'string', 'max:150'],
            'formations.*.filiere' => ['required', 'string', 'max:150'],
            'formations.*.etablissement' => ['required', 'string', 'max:150'],
            'formations.*.niveau' => ['required', 'string', 'in:bac,bac+2,licence,master,doctorat,autre'],
            'formations.*.date_debut' => ['required', 'date'],
            'formations.*.date_fin' => ['nullable', 'date', 'after_or_equal:formations.*.date_debut'],
            'formations.*.en_cours' => ['boolean'],
        ]);

        $this->syncFormations($student->id_candidat, $validated['formations']);

        return response()->json(['message' => 'Formations mises à jour.']);
    }

    public function updateExperiences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $validated = $request->validate([
            'experiences' => ['present', 'array'],
            'experiences.*.type' => ['required', 'string'],
            'experiences.*.nom_entreprise' => ['required', 'string', 'max:150'],
            'experiences.*.description' => ['nullable', 'string'],
            'experiences.*.date_debut' => ['required', 'date'],
            'experiences.*.date_fin' => ['nullable', 'date', 'after_or_equal:experiences.*.date_debut'],
            'experiences.*.competences' => ['nullable', 'array'],
            'experiences.*.competences.*' => ['string', 'max:50'],
        ]);

        $this->syncExperiences($student->id_candidat, $validated['experiences']);

        return response()->json(['message' => 'Expériences mises à jour.']);
    }

    public function updateProjets(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $validated = $request->validate([
            'projets' => ['present', 'array'],
            'projets.*.titre' => ['required', 'string', 'max:150'],
            'projets.*.description' => ['nullable', 'string'],
            'projets.*.technologies' => ['nullable', 'array'],
            'projets.*.technologies.*' => ['string'],
            'projets.*.lien_demo' => ['nullable', 'url'],
            'projets.*.lien_code' => ['nullable', 'url'],
            'projets.*.date' => ['nullable', 'date'],
            'projets.*.image_apercu' => ['nullable', 'image', 'max:2048'],
        ]);

        $this->syncProjets($student->id_candidat, $validated['projets']);

        return response()->json(['message' => 'Projets mis à jour.']);
    }

    public function updateCertificats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $validated = $request->validate([
            'certificats' => ['present', 'array'],
            'certificats.*.titre' => ['required', 'string', 'max:150'],
            'certificats.*.organisme' => ['required', 'string', 'max:150'],
            'certificats.*.date_obtention' => ['required', 'date'],
        ]);

        $this->syncCertificats($student->id_candidat, $validated['certificats']);

        return response()->json(['message' => 'Certificats mis à jour.']);
    }

    public function completeManual(CompleteStudentProfileManualRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Etudiant $student */
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $student, $user): void {
            $photoPath = $student->photo_profil;
            if ($request->hasFile('photo_profil')) {
                $photoPath = $request->file('photo_profil')
                    ->store('profiles/students/' . $user->id_user, 'public');
            }

            $student->update([
                'universite_id' => $validated['universite_id'],
                'date_naissance' => $validated['date_naissance'],
                'telephone' => $validated['telephone'],
                'country' => $validated['country'] ?? null,
                'city' => $validated['city'] ?? null,
                'lien_portfolio' => $validated['lien_portfolio'] ?? null,
                'photo_profil' => $photoPath,
                'profile_mode' => 'manual',
            ]);

            $this->syncCompetences($student->id_candidat, $validated['competences'] ?? []);
            $this->syncFormations($student->id_candidat, $validated['formations'] ?? []);
            $this->syncExperiences($student->id_candidat, $validated['experiences'] ?? []);
            $this->syncProjets($student->id_candidat, $validated['projets'] ?? []);
            $this->syncCertificats($student->id_candidat, $validated['certificats'] ?? []);

            $user->update([
                'profile_completed' => true,
            ]);
        });

        // Logging the profile completion action
        $this->userActivityLogService->log($user, 'update_profile', 'user', $user->id_user, "Student completed modular profile.");

        return response()->json([
            'message' => 'Profil etudiant complete avec succes.',
            'redirect_to' => '/student/dashboard',
        ]);
    }

    public function completeFromCv(CompleteStudentProfileCvRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Etudiant $student */
        $student = $user->etudiant;

        if (! $student) {
            return response()->json(['message' => 'Profil etudiant introuvable.'], 404);
        }

        $cvPath = $request->file('cv_file')
            ->store('profiles/students/' . $user->id_user . '/cv', 'public');

        $student->update([
            'cv_url' => $cvPath,
            'profile_mode' => 'cv',
        ]);

        $user->update([
            'profile_completed' => true,
        ]);

        return response()->json([
            'message' => 'CV importe avec succes. Vous pourrez completer les details ensuite.',
            'redirect_to' => '/student/dashboard',
        ]);
    }

    /**
     * @param  array<int, string>  $competences
     */
    private function syncCompetences(int $studentId, array $competences): void
    {
        DB::table('competence_candidat')->where('id_candidat', $studentId)->delete();

        foreach ($competences as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $competenceId = $this->getOrCreateCompetenceId($name);

            DB::table('competence_candidat')->insert([
                'id_competence' => $competenceId,
                'id_candidat' => $studentId,
            ]);
        }
    }

    private function getOrCreateCompetenceId(string $name, string $type = 'other'): int
    {
        $id = DB::table('competences')->where('nom', $name)->value('id_competence');
        
        if (! $id) {
            $id = DB::table('competences')->insertGetId([
                'nom' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_competence');
        }

        return (int) $id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $formations
     */
    private function syncFormations(int $studentId, array $formations): void
    {
        DB::table('formations')->where('id_candidat', $studentId)->delete();
        foreach ($formations as $formation) {
            DB::table('formations')->insert([
                'id_candidat' => $studentId,
                'diplome' => $formation['diplome'] ?? '',
                'filiere' => $formation['filiere'] ?? '',
                'etablissement' => $formation['etablissement'] ?? '',
                'niveau' => $formation['niveau'] ?? 'autre',
                'date_debut' => $formation['date_debut'] ?? now()->toDateString(),
                'date_fin' => $formation['date_fin'] ?? null,
                'en_cours' => (bool) ($formation['en_cours'] ?? false),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $experiences
     */
    private function syncExperiences(int $studentId, array $experiences): void
    {
        // Many-to-many relationship cleanup is handled differently to avoid deleting needed experiences
        // But for this simple implementation, we delete and recreate as the other methods do
        
        $oldExpIds = DB::table('experiences')->where('id_candidat', $studentId)->pluck('id_experience');
        DB::table('competence_experience')->whereIn('id_experience', $oldExpIds)->delete();
        DB::table('experiences')->where('id_candidat', $studentId)->delete();

        foreach ($experiences as $experience) {
            $expId = DB::table('experiences')->insertGetId([
                'id_candidat' => $studentId,
                'type' => $experience['type'] ?? 'stage',
                'titre' => $experience['titre'] ?? $experience['type'],
                'entreprise_nom' => $experience['entreprise_nom'] ?? $experience['nom_entreprise'] ?? '',
                'description' => $experience['description'] ?? null,
                'date_debut' => $experience['date_debut'] ?? now()->toDateString(),
                'date_fin' => $experience['date_fin'] ?? null,
            ], 'id_experience');

            if (!empty($experience['competences'])) {
                foreach ($experience['competences'] as $skillName) {
                    $skillId = $this->getOrCreateCompetenceId($skillName);
                    DB::table('competence_experience')->insert([
                        'id_experience' => $expId,
                        'id_competence' => $skillId,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $projets
     */
    private function syncProjets(int $studentId, array $projets): void
    {
        $existingProjets = DB::table('projets')->where('id_candidat', $studentId)->get();
        // For simple sync, we delete all and insert.
        // Files are tricky here: we'll try to find existing image path if no new file is uploaded
        DB::table('projets')->where('id_candidat', $studentId)->delete();

        foreach ($projets as $index => $projet) {
            $imagePath = $projet['image_apercu_existing'] ?? null;

            if (is_string($imagePath)) {
                if (str_starts_with($imagePath, 'blob:')) {
                    $imagePath = null;
                } elseif (str_contains($imagePath, '/storage/')) {
                    $path = parse_url($imagePath, PHP_URL_PATH);
                    if (is_string($path)) {
                        $imagePath = ltrim(str_replace('/storage/', '', $path), '/');
                    }
                }
            }

            if (request()->hasFile("projets.{$index}.image_apercu")) {
                $imagePath = request()->file("projets.{$index}.image_apercu")
                    ->store('profiles/students/projects', 'public');
            }

            $projetId = DB::table('projets')->insertGetId([
                'id_candidat' => $studentId,
                'titre' => $projet['titre'] ?? '',
                'description' => $projet['description'] ?? null,
                'lien_demo' => $projet['lien_demo'] ?? null,
                'lien_code' => $projet['lien_code'] ?? null,
                'date' => $projet['date'] ?? null,
                'image_apercu' => $imagePath,
            ], 'id_projet');

            if (!empty($projet['technologies']) && is_array($projet['technologies'])) {
                foreach ($projet['technologies'] as $techName) {
                    $techId = $this->getOrCreateCompetenceId($techName, 'programming');
                    DB::table('projet_technologie')->insert([
                        'id_projet' => $projetId,
                        'id_competence' => $techId,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $certificats
     */
    private function syncCertificats(int $studentId, array $certificats): void
    {
        DB::table('certificats')->where('id_candidat', $studentId)->delete();
        foreach ($certificats as $certificat) {
            DB::table('certificats')->insert([
                'id_candidat' => $studentId,
                'titre' => $certificat['titre'] ?? '',
                'organisme' => $certificat['organisme'] ?? '',
                'date_obtention' => $certificat['date_obtention'] ?? now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
