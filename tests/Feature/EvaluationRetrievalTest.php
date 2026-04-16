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

class EvaluationRetrievalTest extends TestCase
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
    public function can_get_all_evaluations()
    {
        // Create test evaluations
        Evaluation::insert([
            [
                'id_entreprise' => $this->entreprise->id_entreprise,
                'id_candidat' => $this->candidat->id_candidat,
                'evaluator_role' => 'company',
                'note' => 4.5,
                'commentaire' => 'Company evaluates laureat',
                'date_evaluation' => now()->subMinutes(10)
            ],
            [
                'id_entreprise' => $this->entreprise->id_entreprise,
                'id_candidat' => $this->candidat->id_candidat,
                'evaluator_role' => 'student',
                'note' => 5.0,
                'commentaire' => 'Laureat evaluates company',
                'date_evaluation' => now()->subMinutes(5)
            ]
        ]);

        $response = $this->getJson('/api/evaluations');

        $response->assertStatus(200)
                ->assertJsonCount(2)
                ->assertJsonStructure([
                    '*' => [
                        'id_entreprise',
                        'id_candidat',
                        'evaluator_role',
                        'note',
                        'commentaire',
                        'date_evaluation',
                        'entreprise' => [
                            'id_entreprise',
                            'id_user',
                            'nom_entreprise'
                        ],
                        'candidat' => [
                            'id_candidat',
                            'id_user',
                            'nom',
                            'prenom'
                        ]
                    ]
                ]);
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
                ->assertJsonCount(1)
                ->assertJsonStructure([
                    '*' => [
                        'id_entreprise',
                        'id_candidat',
                        'evaluator_role',
                        'note',
                        'commentaire',
                        'date_evaluation',
                        'entreprise' => [
                            'user' => [
                                'id',
                                'nom',
                                'prenom',
                                'email'
                            ]
                        ]
                    ]
                ]);
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
                ->assertJsonCount(1)
                ->assertJsonStructure([
                    '*' => [
                        'id_entreprise',
                        'id_candidat',
                        'evaluator_role',
                        'note',
                        'commentaire',
                        'date_evaluation',
                        'candidat' => [
                            'user' => [
                                'id',
                                'nom',
                                'prenom',
                                'email'
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function can_get_my_candidat_evaluations()
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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->laureatToken
        ])->getJson('/api/evaluations/candidat/me');

        $response->assertStatus(200)
                ->assertJsonCount(1);
    }

    /** @test */
    public function can_get_my_entreprise_evaluations()
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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->companyToken
        ])->getJson('/api/evaluations/entreprise/me');

        $response->assertStatus(200)
                ->assertJsonCount(1);
    }

    /** @test */
    public function my_candidat_evaluations_requires_authentication()
    {
        $response = $this->getJson('/api/evaluations/candidat/me');

        $response->assertStatus(401)
                ->assertJson(['message' => 'Non authentifié']);
    }

    /** @test */
    public function my_entreprise_evaluations_requires_authentication()
    {
        $response = $this->getJson('/api/evaluations/entreprise/me');

        $response->assertStatus(401)
                ->assertJson(['message' => 'Non authentifié']);
    }

    /** @test */
    public function my_candidat_evaluations_requires_candidat_profile()
    {
        // Create user without candidat profile
        $userWithoutProfile = User::factory()->create([
            'email' => 'noprofile@test.com',
            'role' => 'laureat'
        ]);
        $token = JWTAuth::fromUser($userWithoutProfile);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/evaluations/candidat/me');

        $response->assertStatus(404)
                ->assertJson(['message' => 'Candidat non trouvé.']);
    }

    /** @test */
    public function my_entreprise_evaluations_requires_entreprise_profile()
    {
        // Create user without entreprise profile
        $userWithoutProfile = User::factory()->create([
            'email' => 'noprofile@test.com',
            'role' => 'company'
        ]);
        $token = JWTAuth::fromUser($userWithoutProfile);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/evaluations/entreprise/me');

        $response->assertStatus(404)
                ->assertJson(['message' => 'Entreprise non trouvée.']);
    }
}
