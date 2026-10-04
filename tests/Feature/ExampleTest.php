<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La racine redirige (vers les recharges, puis la connexion).
     */
    public function test_the_root_redirects(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
