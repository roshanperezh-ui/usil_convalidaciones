<?php

namespace App\Http\Controllers;

use App\Models\CarreraExterna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de catálogo para cargas en cascada bajo demanda (evita enviar
 * miles de registros al frontend).
 */
class CatalogoController extends Controller
{
    /** Carreras de una institución externa. */
    public function carrerasExternas(Request $request): JsonResponse
    {
        $request->validate(['institucion_id' => ['required', 'exists:instituciones_externas,id']]);

        return response()->json(
            CarreraExterna::where('institucion_id', $request->institucion_id)
                ->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    /** Crea (o reutiliza) una carrera de una institución externa. Todo mantenible. */
    public function crearCarreraExterna(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'institucion_id' => ['required', 'exists:instituciones_externas,id'],
            'nombre'         => ['required', 'string', 'max:200'],
        ]);

        $carrera = CarreraExterna::firstOrCreate([
            'institucion_id' => $datos['institucion_id'],
            'nombre'         => trim($datos['nombre']),
        ]);

        return response()->json(['id' => $carrera->id, 'nombre' => $carrera->nombre]);
    }
}
