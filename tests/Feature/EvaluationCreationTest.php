<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Entreprise;
use App\Models\Candidat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class EvaluationCreationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $companyUser;
    private $laureatUser;
    private $entreprise;
    private $candidat;
    private $companyToken;
    private $laureatToken;

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

        // Generate tokens using Sanctum
        $this->companyUser = Sanctum::actingAs($this->companyUser);
        $this->laureatUser = Sanctum::actingAs($this->laureatUser);
    }

    /** @test */
    public function company_can_evaluate_laureat()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.5,
            'commentaire' => 'Excellent travail!'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'id_entreprise',
                    'id_candidat',
                    'evaluator_role',
                    'note',
                    'commentaire',
                    'date_evaluation'
                ]);

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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->laureatToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'id_entreprise',
                    'id_candidat',
                    'evaluator_role',
                    'note',
                    'commentaire',
                    'date_evaluation'
                ]);

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

        $response->assertStatus(401)
                ->assertJson(['message' => 'Non authentifié']);
    }

    /** @test */
    public function evaluation_requires_valid_note()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 6.0, // Invalid: > 5
            'commentaire' => 'Test'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors'
                ]);
    }

    /** @test */
    public function evaluation_requires_existing_candidat()
    {
        $payload = [
            'id_candidat' => 999, // Non-existent
            'note' => 4.0,
            'commentaire' => 'Test'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422);
    }

    /** @test */
    public function evaluation_requires_existing_entreprise_when_laureat_evaluates()
    {
        $payload = [
            'id_entreprise' => 999, // Non-existent
            'note' => 4.0,
            'commentaire' => 'Test'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->laureatToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422);
    }

    /** @test */
    public function regular_student_cannot_evaluate()
    {
        $studentUser = User::factory()->create([
            'email' => 'student@test.com',
            'role' => 'student'
        ]);

        $studentToken = JWTAuth::fromUser($studentUser);

        $payload = [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'note' => 4.0,
            'commentaire' => 'Test'
        ];

        $response = $this->actingAs($studentUser)->postJson('/api/evaluations', $payload);

        $response->assertStatus(403)
                ->assertJson(['message' => 'Seuls les lauréats et entreprises peuvent évaluer.']);
    }
}
