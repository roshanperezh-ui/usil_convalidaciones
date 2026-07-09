<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function coordinador(): User
    {
        $rol = Role::create(['nombre' => Role::COORDINADOR]);

        return User::create([
            'nombre' => 'Coord', 'email' => 'c@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /** RF-39: el coordinador no accede a la administración de usuarios. */
    public function test_coordinador_no_accede_a_usuarios(): void
    {
        $this->actingAs($this->coordinador())
            ->get('/usuarios')
            ->assertForbidden();
    }
}
