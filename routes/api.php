<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CandidatController;
use App\Http\Controllers\EntrepriseController;
use App\Http\Controllers\CompetenceController;
use App\Http\Controllers\UniversiteController;
use App\Http\Controllers\MissionFreelanceController;
use App\Http\Controllers\OffreEmploiController;
use App\Http\Controllers\OffreStageController;
use App\Http\Controllers\PostulationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\StudentRegistrationController;
use App\Http\Controllers\Auth\CompanyRegistrationController;
use App\Http\Controllers\Auth\UserVerificationController;
use App\Http\Controllers\StudentOnboardingController;
use App\Http\Controllers\CompanyOnboardingController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\CvParserController;
use App\Http\Controllers\Auth\ForgotPasswordController;



Route::middleware(['web'])->group(function () {
    Route::post('/login', [SessionController::class, 'login']);
    Route::post('/logout', [SessionController::class, 'logout']);
    Route::post('/register/student', [StudentRegistrationController::class, 'store']);
    Route::post('/register/company', [CompanyRegistrationController::class, 'store']);
    
    // Unified Verification
    Route::post('/verification/verify-code', [UserVerificationController::class, 'verifyCode']);
    Route::post('/verification/submit-step3', [UserVerificationController::class, 'submitStep3']);
    Route::post('/verification/upload-document', [UserVerificationController::class, 'uploadDocument']);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'talentlink-backend',
    ]);
});


Route::apiResource('users', UserController::class);
Route::apiResource('candidats', CandidatController::class);
Route::apiResource('entreprises', EntrepriseController::class);
Route::apiResource('competences', CompetenceController::class);
Route::get('/universites/search', [UniversiteController::class, 'search']);
Route::apiResource('universites', UniversiteController::class);
Route::apiResource('missions', MissionFreelanceController::class);
Route::apiResource('offres-emploi', OffreEmploiController::class);
Route::apiResource('offres-stage', OffreStageController::class);
Route::get('/offres', [OfferController::class, 'index'])->middleware('web');

    Route::get('/applications', [PostulationController::class, 'index'])->middleware('web');
    Route::post('/applications', [PostulationController::class, 'apply'])->middleware('web');
    
    Route::post('/postulations/emploi', [PostulationController::class, 'applyEmploi']);
Route::post('/postulations/stage', [PostulationController::class, 'applyStage']);
Route::post('/postulations/freelance', [PostulationController::class, 'applyFreelance']);
Route::patch('/postulations/status', [PostulationController::class, 'updateStatus']);




Route::get('/evaluations', [EvaluationController::class, 'index'])->middleware('web');
Route::post('/evaluations', [EvaluationController::class, 'store'])->middleware('web');
Route::get('/evaluations/candidat/{id}', [EvaluationController::class, 'showByCandidat'])->middleware('web');
Route::get('/evaluations/entreprise/{id}', [EvaluationController::class, 'showByEntreprise'])->middleware('web');


Route::post('/cv/parse', [CvParserController::class, 'parseCv']);
Route::get('/notifications', [NotificationController::class, 'index'])->middleware('web');
Route::get('/notifications-unread-count', [NotificationController::class, 'unreadCount'])->middleware('web');
Route::patch('/notifications/{id}', [NotificationController::class, 'update'])->middleware('web');
Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->middleware('web');
Route::get('/storage/applications/docs/{id}/{filename}', function($id, $filename) {
    $path = storage_path('app/public/applications/docs/' . $id . '/' . $filename);
    if (file_exists($path)) {
        return response()->download($path, $filename);
    }
    return response()->json(['error' => 'File not found'], 404);
});
Route::get('/student/profile/data', [StudentOnboardingController::class, 'getProfile'])->middleware('web');
Route::post('/student/profile', [StudentOnboardingController::class, 'completeManual'])->middleware('web');
Route::post('/student/profile/personal-info', [StudentOnboardingController::class, 'updatePersonalInfo'])->middleware('web');
Route::post('/student/profile/competences', [StudentOnboardingController::class, 'updateCompetences'])->middleware('web');
Route::post('/student/profile/formations', [StudentOnboardingController::class, 'updateFormations'])->middleware('web');
Route::post('/student/profile/experiences', [StudentOnboardingController::class, 'updateExperiences'])->middleware('web');
Route::post('/student/profile/projets', [StudentOnboardingController::class, 'updateProjets'])->middleware('web');
Route::post('/student/profile/certificats', [StudentOnboardingController::class, 'updateCertificats'])->middleware('web');
Route::get('/verification/status', function(Request $request) {
    if ($request->email) {
        $user = \App\Models\User::where('email', $request->email)->first();
        if ($user) {
            $verification = $user->latestVerification;
            return response()->json([
                'status' => $verification?->status ?? 'none',
                'message' => $verification?->status ?? 'No verification found',
            ]);
        }
    }
    return response()->json(['status' => 'none', 'message' => 'No user found']);
});

Route::post('/company/verify-email-code', [CompanyOnboardingController::class, 'verifyEmailCode'])->middleware('web');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/evaluations/candidat/me', [EvaluationController::class, 'showMyCandidatEvaluations']);
    Route::get('/evaluations/entreprise/me', [EvaluationController::class, 'showMyEntrepriseEvaluations']);
    
    // Company Profile & Dashboard 
    Route::prefix('company')->middleware('role:company,entreprise')->group(function () {
        Route::get('/dashboard/stats', [EntrepriseController::class, 'getDashboardStats']);
        Route::get('/profile', [EntrepriseController::class, 'getProfile']);
        Route::post('/profile/update', [EntrepriseController::class, 'updateProfile']);
        Route::get('/profile/edit', [CompanyOnboardingController::class, 'profileEdit']);
        Route::post('/profile/complete', [CompanyOnboardingController::class, 'completeProfile']);
        
        // CRUD Offres de Stage
        Route::get('/stages', [OffreStageController::class, 'index']);
        Route::post('/stages', [OffreStageController::class, 'store']);
        Route::put('/stages/{id}', [OffreStageController::class, 'update']);
        Route::delete('/stages/{id}', [OffreStageController::class, 'destroy']);

        // CRUD Offres d'Emploi
        Route::get('/emplois', [OffreEmploiController::class, 'index']);
        Route::post('/emplois', [OffreEmploiController::class, 'store']);
        Route::put('/emplois/{id}', [OffreEmploiController::class, 'update']);
        Route::delete('/emplois/{id}', [OffreEmploiController::class, 'destroy']);

        // CRUD Missions Freelance
        Route::get('/missions', [MissionFreelanceController::class, 'index']);
        Route::post('/missions', [MissionFreelanceController::class, 'store']);
        Route::put('/missions/{id}', [MissionFreelanceController::class, 'update']);
        Route::delete('/missions/{id}', [MissionFreelanceController::class, 'destroy']);
        
        Route::get('/postulations', [PostulationController::class, 'getCompanyPostulations']);
        Route::get('/students/{id}', [CandidatController::class, 'show']);
    });
    
    // Admin Dashboard
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/companies', [AdminDashboardController::class, 'companies']);
        Route::get('/candidates', [AdminDashboardController::class, 'candidates']);
        Route::get('/offers', [AdminDashboardController::class, 'offers']);
        Route::get('/logs', [AdminDashboardController::class, 'logs']);
        Route::get('/accounts/{user}/document', [AdminDashboardController::class, 'showDocument']);
        Route::post('/accounts/{user}/moderate', [AdminDashboardController::class, 'moderate']);
        Route::post('/verification/{id}/decision', [UserVerificationController::class, 'adminDecision']);
    });
});

Route::post('/forgot-password', [ForgotPasswordController::class, 'forgotPassword'])
    ->name('password.email');

Route::post('/verify-reset-code', [ForgotPasswordController::class, 'verifyCode'])
    ->name('password.verify');

Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])
    ->name('password.update');

