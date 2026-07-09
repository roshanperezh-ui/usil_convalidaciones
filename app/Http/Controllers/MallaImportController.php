<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportarMallaRequest;
use App\Jobs\ImportarMallaExcel;
use App\Models\CargaMasiva;
use App\Models\Carrera;
use App\Models\MallaCurricular;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Carga masiva de mallas por Excel (RF-08..12).
 */
class MallaImportController extends Controller
{
    public function create()
    {
        return inertia('Mallas/Importar', [
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(ImportarMallaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        // Crea la malla cabecera (RN-01/03 aplican vía índice único).
        $carga = DB::transaction(function () use ($datos, $request) {
            $malla = MallaCurricular::create([
                'carrera_id'   => $datos['carrera_id'],
                'anio'         => $datos['anio'],
                'version'      => $datos['version'],
                'activa'       => false,
                'origen_carga' => 'excel',
                'usuario_id'   => $request->user()->id,
            ]);

            $ruta = $request->file('archivo')->store('cargas');

            $carga = CargaMasiva::create([
                'usuario_id' => $request->user()->id,
                'malla_id'   => $malla->id,
                'archivo'    => $ruta,
                'estado'     => 'pendiente',
            ]);

            // RF-11: procesamiento en segundo plano.
            ImportarMallaExcel::dispatch($carga->id, $malla->id);

            return $carga;
        });

        return redirect()->route('mallas.importar.estado', $carga->id)
            ->with('status', 'Carga iniciada. El procesamiento corre en segundo plano.');
    }

    public function estado(CargaMasiva $carga)
    {
        return inertia('Mallas/CargaEstado', ['cargaId' => $carga->id]);
    }

    /** Endpoint de progreso para el sondeo del frontend (RF-11). */
    public function progreso(CargaMasiva $carga): JsonResponse
    {
        return response()->json([
            'estado'      => $carga->estado,
            'total'       => $carga->total,
            'procesados'  => $carga->procesados,
            'errores'     => $carga->errores,
            'porcentaje'  => $carga->porcentaje(),
            'detalle'     => $carga->detalle_errores ?? [],
        ]);
    }
}
