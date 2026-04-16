<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Auth\CompanyRegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\StudentRegistrationController;
use App\Http\Controllers\CompanyOnboardingController;
use App\Http\Controllers\StudentOnboardingController;
use App\Http\Controllers\EntrepriseController;
use App\Http\Controllers\OffreStageController;
use App\Http\Controllers\OffreEmploiController;
use App\Http\Controllers\MissionFreelanceController;
use App\Http\Controllers\UniversiteController as UniversityController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\Auth\UserVerificationController;
use App\Http\Controllers\PostulationController;
use App\Http\Controllers\CandidatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EvaluationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Unified Offer Routes
Route::get('/offres', [OfferController::class, 'index']);

// Application Routes
Route::middleware(['auth', 'verified', 'role:student,laureat'])->group(function () {
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
});



// Public Offer Routes (for visitors)
Route::get('/offres/stages', [OffreStageController::class, 'indexPublic']);
Route::get('/offres/stages/{id}', [OffreStageController::class, 'show']);
Route::get('/offres/emplois', [OffreEmploiController::class, 'indexPublic']);
Route::get('/offres/emplois/{id}', [OffreEmploiController::class, 'show']);
Route::get('/offres/missions', [MissionFreelanceController::class, 'indexPublic']);
Route::get('/offres/missions/{id}', [MissionFreelanceController::class, 'show']);

Route::get('/universities/search', [UniversityController::class, 'search'])
    ->name('universities.search');

Route::get('/competences/search', [StudentOnboardingController::class, 'searchCompetences'])
    ->name('competences.search');

Route::post('/register/student', [StudentRegistrationController::class, 'store'])
    ->name('register.student');

Route::post('/register/company', [CompanyRegistrationController::class, 'store'])
    ->name('register.company');

use App\Http\Controllers\Auth\ForgotPasswordController;

Route::post('/forgot-password', [ForgotPasswordController::class, 'forgotPassword'])
    ->name('password.email');

Route::post('/verify-reset-code', [ForgotPasswordController::class, 'verifyCode'])
    ->name('password.verify');

Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])
    ->name('password.update');

Route::post('/login', [SessionController::class, 'login'])
    ->name('login.perform');

Route::post('/logout', [SessionController::class, 'logout'])
    ->middleware('auth')
    ->name('logout.perform');

Route::get('/email/verify', [SessionController::class, 'verificationNotice'])
    ->name('verification.notice');

Route::post('/email/verification-notification', [SessionController::class, 'resendVerificationEmail'])
    ->middleware(['throttle:6,1'])
    ->name('verification.send');

Route::get('/email/verify/{id}/{hash}', [SessionController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/verification/verify-code', [UserVerificationController::class, 'verifyCode'])
    ->name('verification.verify-code');

Route::post('/verification/submit-step3', [UserVerificationController::class, 'submitStep3'])
    ->name('verification.submit-step3');

Route::get('/verification/status', [UserVerificationController::class, 'getStatus'])
    ->name('verification.status');

Route::post('/verification/upload-document', [UserVerificationController::class, 'uploadDocument'])
    ->name('verification.upload-document');

Route::post('/admin/verification/{id}/decision', [UserVerificationController::class, 'adminDecision'])
    ->middleware(['auth', 'role:admin'])
    ->name('admin.verification.decision');

Route::middleware(['auth', 'verified', 'role:student,laureat'])->group(function () {
    Route::get('/student/profile/options', [StudentOnboardingController::class, 'profileOptions'])
        ->name('student.profile.options');

    Route::post('/student/profile/complete/manual', [StudentOnboardingController::class, 'completeManual'])
        ->name('student.profile.complete.manual');

    Route::post('/student/profile/complete/cv', [StudentOnboardingController::class, 'completeFromCv'])
        ->name('student.profile.complete.cv');

    // Granular Profile Management
    Route::get('/student/profile', [StudentOnboardingController::class, 'getProfile'])
        ->name('student.profile.get');
    Route::get('/student/profile/data', [StudentOnboardingController::class, 'getProfile']); // Alternative route
    
    Route::post('/student/profile/personal-info', [StudentOnboardingController::class, 'updatePersonalInfo'])
        ->name('student.profile.update.personal');
    Route::post('/student/profile/competences', [StudentOnboardingController::class, 'updateCompetences'])
        ->name('student.profile.update.competences');
    Route::post('/student/profile/formations', [StudentOnboardingController::class, 'updateFormations'])
        ->name('student.profile.update.formations');
    Route::post('/student/profile/experiences', [StudentOnboardingController::class, 'updateExperiences'])
        ->name('student.profile.update.experiences');
    Route::post('/student/profile/projets', [StudentOnboardingController::class, 'updateProjets'])
        ->name('student.profile.update.projets');
    Route::post('/student/profile/certificats', [StudentOnboardingController::class, 'updateCertificats'])
        ->name('student.profile.update.certificats');

    // Postulations
    Route::get('/student/postulations', [ApplicationController::class, 'index']);
    Route::post('/student/postulations', [ApplicationController::class, 'store']);
});

Route::middleware(['auth', 'role:company,entreprise'])->group(function () {
    Route::get('/company/profile', [EntrepriseController::class, 'getProfile'])
        ->name('company.profile');

    Route::get('/company/dashboard/stats', [EntrepriseController::class, 'getDashboardStats'])
        ->name('company.dashboard.stats');

    Route::post('/company/profile/update', [EntrepriseController::class, 'updateProfile'])
        ->name('company.profile.update');

    Route::get('/company/profile/edit', [CompanyOnboardingController::class, 'profileEdit'])
        ->name('company.profile.edit');

    Route::post('/company/profile/complete', [CompanyOnboardingController::class, 'completeProfile'])
        ->name('company.profile.complete');

    // CRUD Offres de Stage
    Route::get('/company/stages', [OffreStageController::class, 'index']);
    Route::post('/company/stages', [OffreStageController::class, 'store']);
    Route::put('/company/stages/{id}', [OffreStageController::class, 'update']);
    Route::delete('/company/stages/{id}', [OffreStageController::class, 'destroy']);

    // CRUD Offres d'Emploi
    Route::get('/company/emplois', [OffreEmploiController::class, 'index']);
    Route::post('/company/emplois', [OffreEmploiController::class, 'store']);
    Route::put('/company/emplois/{id}', [OffreEmploiController::class, 'update']);
    Route::delete('/company/emplois/{id}', [OffreEmploiController::class, 'destroy']);

    // CRUD Missions Freelance
    Route::get('/company/missions', [MissionFreelanceController::class, 'index']);
    Route::post('/company/missions', [MissionFreelanceController::class, 'store']);
    Route::put('/company/missions/{id}', [MissionFreelanceController::class, 'update']);
    Route::delete('/company/missions/{id}', [MissionFreelanceController::class, 'destroy']);
    Route::get('/company/postulations', [PostulationController::class, 'getCompanyPostulations'])
        ->name('company.postulations');
    Route::get('/company/students/{id}', [CandidatController::class, 'show'])
        ->name('company.student-profile');

    Route::patch('/postulations/status', [PostulationController::class, 'updateStatus'])
        ->name('company.postulations.status');
});

Route::prefix('admin')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');

        Route::get('/companies', [AdminDashboardController::class, 'companies'])
            ->name('admin.companies');

        Route::get('/candidates', [AdminDashboardController::class, 'candidates'])
            ->name('admin.candidates');

        Route::get('/offers', [AdminDashboardController::class, 'offers'])
            ->name('admin.offers');

        Route::get('/logs', [AdminDashboardController::class, 'logs'])
            ->name('admin.logs');

        Route::get('/accounts/{user}/document', [AdminDashboardController::class, 'showDocument'])
            ->name('admin.accounts.document');

        Route::post('/accounts/{user}/moderate', [AdminDashboardController::class, 'moderate'])
            ->name('admin.accounts.moderate');

        Route::get('/profile', [UserController::class, 'profile'])
            ->name('admin.profile');

        Route::put('/profile', [UserController::class, 'updateProfile'])
            ->name('admin.profile.update');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications-unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}', [NotificationController::class, 'update']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Evaluation Routes
    Route::get('/evaluations', [EvaluationController::class, 'index']);
    Route::post('/evaluations', [EvaluationController::class, 'store']);
    Route::get('/evaluations/candidat/me', [EvaluationController::class, 'showMyCandidatEvaluations']);
    Route::get('/evaluations/entreprise/me', [EvaluationController::class, 'showMyEntrepriseEvaluations']);
});

Route::get('/evaluations/candidat/{id}', [EvaluationController::class, 'showByCandidat']);
Route::get('/evaluations/entreprise/{id}', [EvaluationController::class, 'showByEntreprise']);