<?php

namespace Tests\Feature;

use App\Jobs\ImportarMallaExcel;
use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Role;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CargaMasivaTest extends TestCase
{
    use RefreshDatabase;

    /** RF-08/11: la carga válida encola un job en background. */
    public function test_carga_valida_despacha_job(): void
    {
        Queue::fake();
        Storage::fake();

        $rol = Role::create(['nombre' => Role::ADMIN]);
        $user = User::create(['nombre' => 'A', 'email' => 'a@usil.edu.pe', 'password_hash' => Hash::make('x'), 'rol_id' => $rol->id, 'activo' => true, 'primer_acceso' => false]);
        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);

        $archivo = UploadedFile::fake()->create('malla.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user)->post('/mallas/importar', [
            'carrera_id' => $carrera->id, 'anio' => 2026, 'version' => '2026-I', 'archivo' => $archivo,
        ]);

        Queue::assertPushed(ImportarMallaExcel::class);
        $this->assertDatabaseHas('cargas_masivas', ['estado' => 'pendiente']);
        $this->assertDatabaseHas('mallas_curriculares', ['origen_carga' => 'excel']);
    }
}
