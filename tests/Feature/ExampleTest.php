<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * El dashboard exige autenticación: el invitado va al login.
     */
    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
