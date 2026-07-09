<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\Convalidacion;
use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimulacionTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();

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

        Equivalencia::create([
            'carrera_externa_id' => $carExt->id, 'carrera_usil_id' => $carrera->id,
            'curso_externo_id' => $cursoExt->id, 'curso_usil_id' => $cursoUsil->id,
            'tipo_equivalencia' => 'completa', 'origen' => 'manual', 'usuario_id' => $user->id,
        ]);

        $this->ctx = compact('user', 'carrera', 'malla', 'carExt');
    }

    /** RF-26: la simulación genera la tabla comparativa automáticamente. */
    public function test_genera_detalle_automatico(): void
    {
        $this->actingAs($this->ctx['user'])->post('/simulaciones', [
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => '12345678', 'email' => 'ana@x.com', 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $this->ctx['carExt']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'malla_usil_id' => $this->ctx['malla']->id,
        ]);

        $sim = Simulacion::first();
        $this->assertEquals('generada', $sim->estado);
        $this->assertEquals(1, $sim->detalles()->count());
    }

    /** RF-30: confirmar crea convalidación 1:1 e impide duplicado. */
    public function test_confirmar_convalidacion_es_unica(): void
    {
        $sim = Simulacion::create([
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => '999', 'email' => 'a@x.com', 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $this->ctx['carExt']->id, 'carrera_usil_id' => $this->ctx['carrera']->id,
            'malla_usil_id' => $this->ctx['malla']->id, 'estado' => 'generada', 'usuario_id' => $this->ctx['user']->id,
        ]);

        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar");
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar"); // duplicado

        $this->assertEquals(1, Convalidacion::count());
        $this->assertEquals('confirmada', Convalidacion::first()->estado);
    }

    /** RF-46: anular cambia el estado sin eliminar el registro. */
    public function test_anular_convalidacion(): void
    {
        $sim = Simulacion::create([
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => '888', 'email' => 'a@x.com', 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $this->ctx['carExt']->id, 'carrera_usil_id' => $this->ctx['carrera']->id,
            'malla_usil_id' => $this->ctx['malla']->id, 'estado' => 'generada', 'usuario_id' => $this->ctx['user']->id,
        ]);
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar");
        $conv = Convalidacion::first();

        $this->actingAs($this->ctx['user'])->post("/convalidaciones/{$conv->id}/anular", ['motivo' => 'Error de datos']);

        $this->assertEquals('anulada', $conv->fresh()->estado);
        $this->assertEquals(1, Convalidacion::count()); // no se elimina
    }
}
