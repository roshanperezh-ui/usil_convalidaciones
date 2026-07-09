<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\Role;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EquivalenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrar_equivalencia_y_no_duplicar(): void
    {
        $rol = Role::create(['nombre' => Role::ADMIN]);
        $user = User::create(['nombre' => 'A', 'email' => 'a@usil.edu.pe', 'password_hash' => Hash::make('x'), 'rol_id' => $rol->id, 'activo' => true, 'primer_acceso' => false]);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);
        $malla = MallaCurricular::create(['carrera_id' => $carrera->id, 'anio' => 2026, 'version' => 'A', 'origen_carga' => 'manual', 'usuario_id' => $user->id]);
        $ciclo = Ciclo::create(['malla_id' => $malla->id, 'numero' => 1]);
        $cursoUsil = CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => 'U1', 'nombre' => 'Cálculo', 'creditos' => 4]);

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);
        $cursoExt = CursoExterno::create(['carrera_externa_id' => $carExt->id, 'nombre' => 'Matemática I']);

        $payload = [
            'carrera_externa_id' => $carExt->id, 'carrera_usil_id' => $carrera->id,
            'curso_externo_id' => $cursoExt->id, 'curso_usil_id' => $cursoUsil->id,
            'tipo_equivalencia' => 'completa',
        ];

        $this->actingAs($user)->post('/equivalencias', $payload);
        $this->actingAs($user)->post('/equivalencias', $payload); // segundo intento

        $this->assertEquals(1, Equivalencia::count()); // no duplica
    }
}
