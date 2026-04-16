<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Entreprise;
use App\Models\Candidat;
use App\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class EvaluationValidationTest extends TestCase
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

        // Generate tokens
        $this->companyToken = JWTAuth::fromUser($this->companyUser);
        $this->laureatToken = JWTAuth::fromUser($this->laureatUser);
    }

    /** @test */
    public function note_must_be_numeric()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 'not-a-number',
            'commentaire' => 'Test'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['note']);
    }

    /** @test */
    public function note_must_be_between_0_and_5()
    {
        // Test note too low
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => -1,
            'commentaire' => 'Test'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['note']);

        // Test note too high
        $payload['note'] = 6;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['note']);
    }

    /** @test */
    public function note_can_be_decimal()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.5,
            'commentaire' => 'Test with decimal'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('evaluations', [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.5
        ]);
    }

    /** @test */
    public function commentaire_is_optional()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.0
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('evaluations', [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.0,
            'commentaire' => null
        ]);
    }

    /** @test */
    public function commentaire_can_be_long_text()
    {
        $longComment = str_repeat('This is a long comment. ', 50);

        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.0,
            'commentaire' => $longComment
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('evaluations', [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'id_candidat' => $this->candidat->id_candidat,
            'commentaire' => $longComment
        ]);
    }

    /** @test */
    public function statut_mission_is_optional()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.0,
            'commentaire' => 'Test',
            'statut_mission' => 'completed'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(201);
    }

    /** @test */
    public function company_cannot_evaluate_without_entreprise_profile()
    {
        // Create company user without entreprise profile
        $companyUserNoProfile = User::factory()->create([
            'email' => 'noprofile@test.com',
            'role' => 'company'
        ]);
        $token = JWTAuth::fromUser($companyUserNoProfile);

        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.0,
            'commentaire' => 'Test'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Le profil doit être complété pour pouvoir évaluer (ID manquant).'
                ]);
    }

    /** @test */
    public function laureat_cannot_evaluate_without_candidat_profile()
    {
        // Create laureat user without candidat profile
        $laureatUserNoProfile = User::factory()->create([
            'email' => 'noprofile@test.com',
            'role' => 'laureat'
        ]);
        $token = JWTAuth::fromUser($laureatUserNoProfile);

        $payload = [
            'id_entreprise' => $this->entreprise->id_entreprise,
            'note' => 4.0,
            'commentaire' => 'Test'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Le profil doit être complété pour pouvoir évaluer (ID manquant).'
                ]);
    }

    /** @test */
    public function evaluation_payload_validation_errors_are_detailed()
    {
        $payload = [
            'id_candidat' => 'invalid',
            'note' => 'invalid',
            'commentaire' => 123 // Should be string
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors',
                    'received_payload'
                ]);
    }

    /** @test */
    public function duplicate_evaluation_is_allowed_with_different_timestamps()
    {
        $payload = [
            'id_candidat' => $this->candidat->id_candidat,
            'note' => 4.0,
            'commentaire' => 'First evaluation'
        ];

        // First evaluation
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response1->assertStatus(201);

        // Wait a moment to ensure different timestamp
        sleep(1);

        // Second evaluation (should be allowed due to different timestamp)
        $payload['commentaire'] = 'Second evaluation';
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->postJson('/api/evaluations', $payload);

        $response2->assertStatus(201);

        // Verify both evaluations exist
        $this->assertEquals(2, Evaluation::where('id_entreprise', $this->entreprise->id_entreprise)
            ->where('id_candidat', $this->candidat->id_candidat)
            ->where('evaluator_role', 'company')
            ->count());
    }
}
