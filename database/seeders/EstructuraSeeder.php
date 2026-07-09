<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Modalidad;
use App\Models\PlanEstudio;
use App\Models\UnidadNegocio;
use Illuminate\Database\Seeder;

/**
 * Datos base de la Estructura Institucional: códigos/dirección de sedes,
 * modalidades y un plan de estudios de demostración.
 *
 * Ejecutar: php artisan db:seed --class=EstructuraSeeder
 */
class EstructuraSeeder extends Seeder
{
    public function run(): void
    {
        // --- Sedes: completar código y dirección de las existentes ---
        $sedes = [
            ['USIL Lima', 'SEDE-LIMA', 'Av. La Fontana 550, La Molina, Lima'],
            ['USIL Virtual', 'SEDE-VIRT', 'Plataforma de educación a distancia'],
        ];
        foreach ($sedes as [$nombre, $codigo, $direccion]) {
            UnidadNegocio::where('nombre', $nombre)->update(['codigo' => $codigo, 'direccion' => $direccion]);
        }
        // Cualquier otra sede sin código recibe uno generado.
        UnidadNegocio::whereNull('codigo')->get()->each(function (UnidadNegocio $s) {
            $s->update(['codigo' => 'SEDE-' . str_pad((string) $s->id, 3, '0', STR_PAD_LEFT)]);
        });

        // --- Modalidades ---
        foreach ([['PRE', 'Presencial'], ['SEM', 'Semipresencial'], ['VIRT', 'Virtual']] as [$codigo, $nombre]) {
            Modalidad::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }

        // --- Programa demo: grado y título ---
        Carrera::where('codigo', 'ISI')->update([
            'grado_academico'    => 'Bachiller en Ingeniería de Sistemas de Información',
            'titulo_profesional' => 'Ingeniero de Sistemas de Información',
        ]);

        // --- Plan de estudios demo ---
        $isi = Carrera::where('codigo', 'ISI')->first();
        $presencial = Modalidad::where('codigo', 'PRE')->first();
        if ($isi && $presencial) {
            PlanEstudio::updateOrCreate(
                ['codigo' => 'PLAN-ISI-2024-PRE'],
                [
                    'carrera_id'   => $isi->id,
                    'modalidad_id' => $presencial->id,
                    'nombre'       => 'Plan de Estudios ISI 2024',
                    'anio'         => 2024,
                    'version'      => 'v1.0',
                    'activo'       => true,
                ]
            );
        }

        $this->command->info('Estructura sembrada: sedes con código, 3 modalidades y 1 plan de estudios demo.');
    }
}
