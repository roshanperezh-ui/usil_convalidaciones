<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración para probar el flujo completo de convalidación
 * (mallas → instituciones → equivalencias → simulación → PDF). Idempotente.
 *
 * Ejecutar: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@usil.edu.pe')->firstOrFail();

        // --- Estructura USIL ---
        $unidad = UnidadNegocio::firstOrCreate(['nombre' => 'USIL Lima']);

        $facultad = Facultad::firstOrCreate(
            ['codigo' => 'FIN'],
            ['unidad_negocio_id' => $unidad->id, 'nombre' => 'Facultad de Ingeniería']
        );

        $carrera = Carrera::firstOrCreate(
            ['codigo' => 'ISI'],
            ['facultad_id' => $facultad->id, 'nombre' => 'Ingeniería de Sistemas de Información', 'max_ciclos' => 10]
        );

        $malla = MallaCurricular::updateOrCreate(
            ['carrera_id' => $carrera->id, 'anio' => 2024, 'version' => '2024-1'],
            ['modalidad' => 'presencial', 'periodo' => '2024-01', 'activa' => true, 'origen_carga' => 'manual', 'usuario_id' => $admin->id]
        );

        $c1 = Ciclo::firstOrCreate(['malla_id' => $malla->id, 'numero' => 1], ['nombre' => 'Ciclo 1']);
        $c2 = Ciclo::firstOrCreate(['malla_id' => $malla->id, 'numero' => 2], ['nombre' => 'Ciclo 2']);

        $cursos = [
            'MAT101' => [$c1, 'Cálculo I', 4.0],
            'PRG101' => [$c1, 'Fundamentos de Programación', 4.0],
            'COM101' => [$c1, 'Comunicación I', 3.0],
            'MAT102' => [$c2, 'Cálculo II', 4.0],
            'PRG102' => [$c2, 'Programación Orientada a Objetos', 4.0],
        ];
        $cursoUsil = [];
        foreach ($cursos as $cod => [$ciclo, $nom, $cred]) {
            $cursoUsil[$cod] = CursoUsil::firstOrCreate(
                ['ciclo_id' => $ciclo->id, 'codigo' => $cod],
                ['nombre' => $nom, 'creditos' => $cred]
            );
        }

        // --- Institución externa ---
        $tipoUniv = TipoInstitucion::where('nombre', 'Universidad')->firstOrFail();

        $inst = InstitucionExterna::firstOrCreate(
            ['nombre' => 'Universidad Nacional Mayor de San Marcos'],
            ['tipo_id' => $tipoUniv->id, 'pais' => 'Perú']
        );

        $carreraExt = CarreraExterna::firstOrCreate(
            ['institucion_id' => $inst->id, 'nombre' => 'Ingeniería de Software']
        );

        $cursosExt = [
            'EXT-MAT1' => ['Matemática I', 4.0],
            'EXT-PRG1' => ['Introducción a la Programación', 4.0],
            'EXT-COM1' => ['Redacción y Comunicación', 3.0],
            'EXT-MAT2' => ['Matemática II', 4.0],
        ];
        $cursoExterno = [];
        foreach ($cursosExt as $cod => [$nom, $cred]) {
            $cursoExterno[$cod] = CursoExterno::firstOrCreate(
                ['carrera_externa_id' => $carreraExt->id, 'codigo' => $cod],
                ['nombre' => $nom, 'creditos' => $cred]
            );
        }

        // --- Equivalencias (cruzadas por carrera externa ↔ carrera USIL) ---
        $map = [
            ['EXT-MAT1', 'MAT101', 'completa'],
            ['EXT-PRG1', 'PRG101', 'completa'],
            ['EXT-COM1', 'COM101', 'parcial'],
            ['EXT-MAT2', 'MAT102', 'completa'],
        ];
        foreach ($map as [$ext, $usil, $tipo]) {
            Equivalencia::firstOrCreate(
                [
                    'carrera_externa_id' => $carreraExt->id,
                    'carrera_usil_id'    => $carrera->id,
                    'curso_externo_id'   => $cursoExterno[$ext]->id,
                    'curso_usil_id'      => $cursoUsil[$usil]->id,
                ],
                ['tipo_equivalencia' => $tipo, 'origen' => 'manual', 'usuario_id' => $admin->id]
            );
        }

        // --- Catálogo ampliado de mallas (para poblar filtros, tabla y paginación) ---
        // Estructura: unidad => facultad (código) => carreras => mallas [año, versión, activa, origen]
        // Mallas: [año, versión, activa, origen, modalidad, periodo]
        $catalogo = [
            ['USIL Lima', 'Facultad de Ciencias Empresariales', 'FCE', [
                ['Administración de Empresas', 'ADM', 10, [[2024, 'v2.0', true, 'excel', 'presencial', '2024-01'], [2020, 'v1.2', false, 'manual', 'presencial', '2020-01']]],
                ['Contabilidad', 'CON', 10, [[2022, 'v1.5', true, 'manual', 'hibrido', '2022-01']]],
                ['Marketing', 'MKT', 10, [[2023, 'v1.0', true, 'excel', 'virtual', '2023-02']]],
            ]],
            ['USIL Lima', 'Facultad de Ingeniería', 'FIN', [
                ['Ingeniería Industrial y Comercial', 'IIC', 10, [[2023, 'v1.0', true, 'manual', 'presencial', '2023-01'], [2021, 'v0.9', false, 'excel', 'presencial', '2021-01']]],
            ]],
            ['USIL Lima', 'Facultad de Ciencias de la Salud', 'FCS', [
                ['Medicina Humana', 'MED', 14, [[2023, 'v1.0', true, 'manual', 'presencial', '2023-01'], [2019, 'v0.8', false, 'manual', 'presencial', '2019-01']]],
                ['Nutrición y Dietética', 'NUT', 10, [[2024, 'v1.0', true, 'excel', 'hibrido', '2024-01']]],
            ]],
            ['USIL Virtual', 'Escuela de Postgrado', 'EPG', [
                ['MBA Ejecutivo', 'MBA', 6, [[2023, 'v2.5', true, 'manual', 'virtual', '2023-02']]],
            ]],
        ];

        foreach ($catalogo as [$unidadNombre, $facNombre, $facCodigo, $carrerasDef]) {
            $u = UnidadNegocio::firstOrCreate(['nombre' => $unidadNombre]);
            $f = Facultad::firstOrCreate(['codigo' => $facCodigo], ['unidad_negocio_id' => $u->id, 'nombre' => $facNombre]);

            foreach ($carrerasDef as [$carrNombre, $carrCodigo, $maxCiclos, $mallasDef]) {
                $car = Carrera::firstOrCreate(
                    ['codigo' => $carrCodigo],
                    ['facultad_id' => $f->id, 'nombre' => $carrNombre, 'max_ciclos' => $maxCiclos]
                );

                foreach ($mallasDef as [$anioM, $ver, $activa, $origen, $modalidad, $periodo]) {
                    MallaCurricular::updateOrCreate(
                        ['carrera_id' => $car->id, 'anio' => $anioM, 'version' => $ver],
                        ['modalidad' => $modalidad, 'periodo' => $periodo, 'activa' => $activa, 'origen_carga' => $origen, 'usuario_id' => $admin->id]
                    );
                }
            }
        }

        // --- Instituciones externas adicionales (para poblar filtros, tabla y paginación) ---
        $tipoInst = TipoInstitucion::where('nombre', 'Instituto')->firstOrFail();
        $institucionesDemo = [
            ['Pontificia Universidad Católica del Perú', $tipoUniv->id, 'Perú', true, ['Ingeniería Informática', 'Administración']],
            ['Universidad de Buenos Aires', $tipoUniv->id, 'Argentina', true, ['Licenciatura en Sistemas']],
            ['Tecnológico de Monterrey', $tipoUniv->id, 'México', false, ['Ingeniería en TI']],
            ['SENATI', $tipoInst->id, 'Perú', true, ['Mecatrónica Industrial', 'Desarrollo de Software']],
            ['Universidad Continental', $tipoUniv->id, 'Perú', true, ['Ingeniería de Sistemas e Informática']],
        ];
        foreach ($institucionesDemo as [$nombreInst, $tipoId, $pais, $activaInst, $carrerasNombres]) {
            $inst2 = InstitucionExterna::firstOrCreate(
                ['nombre' => $nombreInst],
                ['tipo_id' => $tipoId, 'pais' => $pais, 'activa' => $activaInst]
            );
            foreach ($carrerasNombres as $cn) {
                $inst2->carreras()->firstOrCreate(['nombre' => $cn]);
            }
        }

        // --- Usuarios de prueba (listos para usar, sin cambio forzado de contraseña) ---
        $rolAdmin = Role::where('nombre', Role::ADMIN)->firstOrFail();
        $rolCoord = Role::where('nombre', Role::COORDINADOR)->firstOrFail();

        $adminDemo = User::updateOrCreate(
            ['email' => 'admin.demo@usil.edu.pe'],
            [
                'nombre'        => 'Admin de Prueba',
                'password_hash' => Hash::make('Demo#1234'),
                'rol_id'        => $rolAdmin->id,
                'activo'        => true,
                'primer_acceso' => false,
            ]
        );

        $coordDemo = User::updateOrCreate(
            ['email' => 'coord.demo@usil.edu.pe'],
            [
                'nombre'        => 'Coordinador de Prueba',
                'password_hash' => Hash::make('Demo#1234'),
                'rol_id'        => $rolCoord->id,
                'activo'        => true,
                'primer_acceso' => false,
            ]
        );

        // RF-40: el coordinador solo ve la carrera ISI.
        $coordDemo->carrerasPermitidas()->syncWithoutDetaching([$carrera->id]);

        $this->command->info('Demo sembrada: carrera ISI (malla 2024-1, 5 cursos), UNMSM Ing. de Software (4 cursos) y 4 equivalencias.');
        $this->command->info('Usuarios de prueba: admin.demo@usil.edu.pe y coord.demo@usil.edu.pe (contraseña Demo#1234).');
    }
}
