<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationSimpleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function evaluation_test_works()
    {
        $response = $this->get('/api/evaluations');

        $response->assertStatus(200);
    }

    /** @test */
    public function evaluation_endpoint_returns_json()
    {
        $response = $this->get('/api/evaluations');

        $response->assertHeader('content-type', 'application/json');
    }
}
