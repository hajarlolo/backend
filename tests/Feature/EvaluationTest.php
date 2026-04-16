<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Entreprise;
use App\Models\Candidat;
use App\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class EvaluationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $companyUser;
    private $laureatUser;
    private $entreprise;
    private $candidat;

    protected function setUp(): void
    {
        parent::setUp();

        // Create company user and entreprise
        $this->companyUser = User::factory()->create([
            'email' => 'company@test.com',
            'role' => 'company'
        ]);
        
        $this->entreprise = Entreprise::factory()->create([
            'id_user' => $this->companyUser->id,
            'id_entreprise' => 1
        ]);

        // Create laureat user and candidat
        $this->laureatUser = User::factory()->create([
            'email' => 'laureat@test.com',
            'role' => 'laureat'
        ]);
        
        $this->candidat = Candidat::factory()->create([
            'id_user' => $this->laureatUser->id,
            'id_candidat' => 1
        ]);
    }

    /** @test */
    public function can_get_all_evaluations()
    {
        // Create test evaluation
        Evaluation::insert([
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'evaluator_role' => 'company',
            'note' => 4.5,
            'commentaire' => 'Company evaluates laureat',
            'date_evaluation' => now()
        ]);

        $response = $this->getJson('/api/evaluations');

        $response->assertStatus(200)
                ->assertJsonCount(1);
    }

    /** @test */
    public function company_can_evaluate_laureat()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.5,
            'commentaire' => 'Excellent travail!'
        ];

        $response = $this->actingAs($this->companyUser)->postJson('/api/evaluations', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('evaluations', [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'evaluator_role' => 'company',
            'note' => 4.5,
            'commentaire' => 'Excellent travail!'
        ]);
    }

    /** @test */
    public function laureat_can_evaluate_company()
    {
        $payload = [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'note' => 5.0,
            'commentaire' => 'Super entreprise!'
        ];

        $response = $this->actingAs($this->laureatUser)->postJson('/api/evaluations', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('evaluations', [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'evaluator_role' => 'student',
            'note' => 5.0,
            'commentaire' => 'Super entreprise!'
        ]);
    }

    /** @test */
    public function evaluation_requires_authentication()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.0,
            'commentaire' => 'Test'
        ];

        $response = $this->postJson('/api/evaluations', $payload);

        $response->assertStatus(401);
    }

    /** @test */
    public function evaluation_requires_valid_note()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 6.0, // Invalid: > 5
            'commentaire' => 'Test'
        ];

        $response = $this->actingAs($this->companyUser)->postJson('/api/evaluations', $payload);

        $response->assertStatus(422);
    }

    /** @test */
    public function can_get_evaluations_by_candidat()
    {
        // Create test evaluation
        Evaluation::insert([
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'evaluator_role' => 'company',
            'note' => 4.5,
            'commentaire' => 'Company evaluates laureat',
            'date_evaluation' => now()
        ]);

        $response = $this->getJson("/api/evaluations/candidat/{$this->candidat->id_candidat}");

        $response->assertStatus(200)
                ->assertJsonCount(1);
    }

    /** @test */
    public function can_get_evaluations_by_entreprise()
    {
        // Create test evaluation
        Evaluation::insert([
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'evaluator_role' => 'student',
            'note' => 5.0,
            'commentaire' => 'Laureat evaluates company',
            'date_evaluation' => now()
        ]);

        $response = $this->getJson("/api/evaluations/entreprise/{$this->entreprise->id_entreprise}");

        $response->assertStatus(200)
                ->assertJsonCount(1);
    }

    /** @test */
    public function regular_student_cannot_evaluate()
    {
        $studentUser = User::factory()->create([
            'email' => 'student@test.com',
            'role' => 'student'
        ]);

        $payload = [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'note' => 4.0,
            'commentaire' => 'Test'
        ];

        $response = $this->actingAs($studentUser)->postJson('/api/evaluations', $payload);

        $response->assertStatus(403);
    }
}
