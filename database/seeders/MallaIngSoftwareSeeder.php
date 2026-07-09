<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\CursoUsil;
use App\Models\MallaCurricular;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Malla curricular de la carrera de Ingeniería de Software (USIL).
 * Año 2023, versión v1, activa. Idempotente.
 *
 * Estructura de $CURSOS: ciclo => [ [codigo, nombre, creditos, horasTeoria, horasPractica, prerequisitoCodigo], ... ]
 * Donde no se conozca un dato, usar null (se mostrará como "-").
 *
 * Ejecutar: php artisan db:seed --class=MallaIngSoftwareSeeder
 */
class MallaIngSoftwareSeeder extends Seeder
{
    /** Nº de ciclos de la malla. */
    private const NUM_CICLOS = 10;

    /**
     * Cursos por ciclo. Completar con los datos del Excel oficial.
     * Formato: nº de ciclo => [ [codigo, nombre, creditos, horasTeoria, horasPractica, prerequisitoCodigo], ... ]
     */
    private const CURSOS = [
        // 1 => [
        //     ['IS101', 'Introducción a la Ingeniería de Software', 3.0, 2, 2, null],
        // ],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@usil.edu.pe')->firstOrFail();

        $carrera = Carrera::where('nombre', 'Ingeniería de Software')->firstOrFail();

        $malla = MallaCurricular::updateOrCreate(
            ['carrera_id' => $carrera->id, 'anio' => 2023, 'version' => 'v1'],
            [
                'modalidad'    => 'presencial',
                'periodo'      => '2023-01',
                'activa'       => true,
                'origen_carga' => 'manual',
                'usuario_id'   => $admin->id,
            ]
        );

        // Crea los ciclos 1..N.
        $ciclos = [];
        for ($n = 1; $n <= self::NUM_CICLOS; $n++) {
            $ciclos[$n] = Ciclo::firstOrCreate(
                ['malla_id' => $malla->id, 'numero' => $n],
                ['nombre' => "Ciclo {$n}"]
            );
        }

        // Primera pasada: crea/actualiza los cursos (sin prerrequisitos).
        $porCodigo = [];
        foreach (self::CURSOS as $numCiclo => $cursos) {
            foreach ($cursos as [$codigo, $nombre, $creditos, $ht, $hp, $prereq]) {
                $curso = CursoUsil::updateOrCreate(
                    ['ciclo_id' => $ciclos[$numCiclo]->id, 'codigo' => $codigo],
                    [
                        'nombre'         => $nombre,
                        'creditos'       => $creditos,
                        'horas_teoria'   => $ht,
                        'horas_practica' => $hp,
                    ]
                );
                $porCodigo[$codigo] = ['curso' => $curso, 'prereq' => $prereq];
            }
        }

        // Segunda pasada: enlaza prerrequisitos por código (ya existen todos).
        foreach ($porCodigo as ['curso' => $curso, 'prereq' => $prereq]) {
            if ($prereq && isset($porCodigo[$prereq])) {
                $curso->update(['prerequisito_id' => $porCodigo[$prereq]['curso']->id]);
            }
        }

        $totalCursos = array_sum(array_map('count', self::CURSOS));
        $this->command->info("Malla Ingeniería de Software 2023/v1 sembrada: {$this->numCiclos()} ciclos, {$totalCursos} cursos.");
    }

    private function numCiclos(): int
    {
        return self::NUM_CICLOS;
    }
}
