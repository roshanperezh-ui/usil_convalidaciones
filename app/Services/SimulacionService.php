<?php

namespace App\Services;

use App\Models\Simulacion;

/**
 * Utilidades de cálculo sobre una simulación de convalidación.
 * (La generación del detalle vive en SimulacionController::persistirSimulacion.)
 */
class SimulacionService
{
    /** Créditos reconocidos vigentes (filas no excluidas). */
    public function creditosReconocidos(Simulacion $simulacion): float
    {
        return (float) $simulacion->detalles()->where('excluido', false)->sum('creditos_reconocidos');
    }
}
