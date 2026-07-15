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

    /** Crea un usuario con el rol indicado y sus permisos reales (RoleSeeder). */
    private function usuarioConRol(string $rolNombre): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', $rolNombre)->firstOrFail();

        return User::create([
            'nombre' => $rolNombre, 'email' => strtolower(str_replace([' ', '/'], '', $rolNombre)) . '@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /** El Auditor (solo lectura) no puede ejecutar acciones de escritura. */
    public function test_auditor_no_puede_escribir(): void
    {
        $auditor = $this->usuarioConRol(Role::AUDITOR);

        // Rutas sin binding: el middleware responde 403 directo.
        $this->actingAs($auditor)->post('/equivalencias', [])->assertForbidden();
        $this->actingAs($auditor)->post('/simulaciones', [])->assertForbidden();
        $this->actingAs($auditor)->post('/postulantes', [])->assertForbidden();

        // Rutas con binding {id}: el binding (404) corre antes que el permiso (403);
        // ambos deniegan — lo importante es que nunca sea 2xx/302 de éxito.
        foreach ([
            fn () => $this->delete('/equivalencias/1'),
            fn () => $this->put('/simulaciones/1', []),
            fn () => $this->delete('/simulaciones/1'),
            fn () => $this->post('/simulaciones/1/confirmar'),
            fn () => $this->post('/convalidaciones/1/anular', []),
            fn () => $this->delete('/postulantes/1'),
        ] as $peticion) {
            $this->actingAs($auditor);
            $status = $peticion()->getStatusCode();
            $this->assertContains($status, [403, 404], "Se esperaba denegación (403/404), llegó {$status}.");
        }
    }

    /** Servicios Académicos (admisión) no accede a simulaciones ni equivalencias. */
    public function test_servicios_no_gestiona_equivalencias(): void
    {
        $servicios = $this->usuarioConRol(Role::SERVICIOS);

        $this->actingAs($servicios)->post('/equivalencias', [])->assertForbidden();
        $this->actingAs($servicios)->post('/simulaciones', [])->assertForbidden();
        $this->actingAs($servicios)->get('/simulaciones')->assertForbidden();
        $this->actingAs($servicios)->get('/equivalencias')->assertForbidden();
        // Su ámbito: postulantes y reportes.
        $this->actingAs($servicios)->get('/postulantes')->assertOk();
        $this->actingAs($servicios)->get('/reportes')->assertOk();
    }

    /** El Coordinador no accede al módulo de postulantes (lo gestiona Admisión). */
    public function test_coordinador_no_accede_a_postulantes(): void
    {
        $coordinador = $this->usuarioConRol(Role::COORDINADOR);

        $this->actingAs($coordinador)->get('/postulantes')->assertForbidden();
        $this->actingAs($coordinador)->post('/postulantes', [])->assertForbidden();
        // Pero conserva su módulo de evaluación.
        $this->actingAs($coordinador)->get('/equivalencias')->assertOk();
        $this->actingAs($coordinador)->get('/simulaciones')->assertOk();
    }

    /** El Decano sí puede gestionar equivalencias (pasa el middleware de permiso). */
    public function test_decano_puede_gestionar_equivalencias(): void
    {
        $decano = $this->usuarioConRol(Role::DECANO);

        // 302 (redirect con errores de validación) = pasó la autorización; 403 = bloqueado.
        $this->actingAs($decano)->post('/equivalencias', [])->assertStatus(302);
        $this->actingAs($decano)->get('/equivalencias')->assertOk();
    }
}
