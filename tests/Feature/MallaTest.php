<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\MallaCurricular;
use App\Models\Role;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MallaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $rol = Role::create(['nombre' => Role::ADMIN]);

        return User::create([
            'nombre' => 'Admin', 'email' => 'a@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    private function carrera(): Carrera
    {
        $un = UnidadNegocio::create(['nombre' => 'USIL Lima']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ingeniería', 'codigo' => 'ING']);

        return Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'Ing. de Software', 'codigo' => 'SW', 'max_ciclos' => 10]);
    }

    /** TC-01 / RF-03: rechazar malla duplicada (RN-01, RN-03). */
    public function test_no_permite_malla_duplicada(): void
    {
        $admin = $this->admin();
        $carrera = $this->carrera();

        MallaCurricular::create([
            'carrera_id' => $carrera->id, 'anio' => 2026, 'version' => '2026-I',
            'origen_carga' => 'manual', 'usuario_id' => $admin->id,
        ]);

        $resp = $this->actingAs($admin)->post('/mallas', [
            'carrera_id' => $carrera->id, 'anio' => 2026, 'version' => '2026-I',
            'ciclos' => [['numero' => 1, 'cursos' => [['codigo' => 'C1', 'nombre' => 'Curso 1', 'creditos' => 4]]]],
        ]);

        $resp->assertSessionHasErrors('version_unica');
        $this->assertEquals(1, MallaCurricular::count());
    }

    /** RF-01/02: alta manual correcta crea malla, ciclo y curso. */
    public function test_alta_manual_crea_estructura(): void
    {
        $admin = $this->admin();
        $carrera = $this->carrera();

        $this->actingAs($admin)->post('/mallas', [
            'carrera_id' => $carrera->id, 'anio' => 2026, 'version' => '2026-II', 'activa' => true,
            'ciclos' => [['numero' => 1, 'cursos' => [['codigo' => 'MAT101', 'nombre' => 'Cálculo', 'creditos' => 5]]]],
        ])->assertRedirect('/mallas');

        $this->assertDatabaseHas('mallas_curriculares', ['version' => '2026-II', 'activa' => true]);
        $this->assertDatabaseHas('cursos_usil', ['codigo' => 'MAT101']);
    }
}
