<?php

namespace App\Jobs;

use App\Imports\MallaCursosImport;
use App\Models\CargaMasiva;
use App\Models\MallaCurricular;
use App\Services\AuditoriaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * RF-11: procesa la carga masiva en background mostrando progreso.
 * RF-10: normaliza y distribuye en ciclos/cursos. RF-12: logs por línea.
 */
class ImportarMallaExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $cargaId, public int $mallaId) {}

    public function handle(): void
    {
        $carga = CargaMasiva::findOrFail($this->cargaId);
        $malla = MallaCurricular::findOrFail($this->mallaId);

        $carga->update(['estado' => 'procesando', 'malla_id' => $malla->id]);

        // Lee todas las hojas y elige la de datos (la que tiene la columna 'codigo');
        // así una plantilla con hoja extra de "Instrucciones" no rompe la importación.
        $hojas = Excel::toCollection(new MallaCursosImport, Storage::path($carga->archivo));
        $filas = collect();
        foreach ($hojas as $hoja) {
            if ($hoja->isNotEmpty() && collect($hoja->first())->keys()->contains('codigo')) {
                $filas = $hoja;
                break;
            }
        }
        if ($filas->isEmpty()) {
            $filas = $hojas->first() ?? collect();
        }

        $carga->update(['total' => $filas->count()]);

        $errores = [];
        $procesados = 0;

        foreach ($filas as $idx => $fila) {
            $linea = $idx + 2; // +1 encabezado, +1 base 1

            try {
                $this->validarFila($fila, $linea);

                DB::transaction(function () use ($malla, $fila) {
                    $ciclo = $malla->ciclos()->firstOrCreate(['numero' => (int) $fila['ciclo']]);

                    $opcional = fn ($k) => isset($fila[$k]) && $fila[$k] !== '' ? (float) $fila[$k] : null;

                    $mencion = isset($fila['mencion']) && trim((string) $fila['mencion']) !== ''
                        ? trim((string) $fila['mencion'])
                        : null;

                    $ciclo->cursos()->create([
                        'codigo' => trim((string) $fila['codigo']),
                        'nombre' => trim((string) $fila['nombre']),
                        'creditos' => (float) $fila['creditos'],
                        'horas_teoria' => $opcional('horas_teoria'),
                        'horas_practica' => $opcional('horas_practica'),
                        // 'caracter' = Electivo / Obligatorio (opcional, por defecto obligatorio).
                        'es_electivo' => isset($fila['caracter'])
                            ? str_starts_with(mb_strtolower(trim((string) $fila['caracter'])), 'electiv')
                            : false,
                        // 'mencion' (opcional) = especialidad; vacío = curso del plan regular.
                        'mencion' => $mencion,
                    ]);
                });

                $procesados++;
            } catch (Throwable $e) {
                $errores[] = ['linea' => $linea, 'mensaje' => $e->getMessage()];
            }

            $carga->update(['procesados' => $procesados, 'errores' => count($errores)]);
        }

        $carga->update([
            'estado' => count($errores) === $filas->count() && $filas->count() > 0 ? 'fallido' : 'completado',
            'detalle_errores' => $errores,
        ]);

        AuditoriaService::registrar('crear', 'mallas_curriculares', $malla->id, null, [
            'carga' => $carga->id, 'procesados' => $procesados, 'errores' => count($errores),
        ]);
    }

    private function validarFila($fila, int $linea): void
    {
        // RF-09: validar que no haya conflictos / datos inválidos.
        foreach (['ciclo', 'codigo', 'nombre', 'creditos'] as $col) {
            if (! isset($fila[$col]) || $fila[$col] === null || $fila[$col] === '') {
                throw new \RuntimeException("Línea $linea: columna '$col' vacía.");
            }
        }

        if (! is_numeric($fila['creditos']) || (float) $fila['creditos'] <= 0) {
            throw new \RuntimeException("Línea $linea: créditos inválidos.");
        }

        if ((int) $fila['ciclo'] < 1 || (int) $fila['ciclo'] > 14) {
            throw new \RuntimeException("Línea $linea: número de ciclo fuera de rango (1-14).");
        }
    }

    public function failed(Throwable $e): void
    {
        CargaMasiva::where('id', $this->cargaId)->update([
            'estado' => 'fallido',
            'detalle_errores' => [['linea' => 0, 'mensaje' => $e->getMessage()]],
        ]);
    }
}
