<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registra toda operación relevante en auditoria_log (RNF-08).
 */
class AuditoriaService
{
    public static function registrar(
        string $accion,
        string $tablaAfectada,
        ?int $registroId = null,
        ?array $anteriores = null,
        ?array $nuevos = null
    ): void {
        AuditoriaLog::create([
            'usuario_id'         => Auth::id(),
            'accion'             => $accion,
            'tabla_afectada'     => $tablaAfectada,
            'registro_id'        => $registroId,
            'valores_anteriores' => $anteriores,
            'valores_nuevos'     => $nuevos,
            'ip_origen'          => Request::ip(),
            'created_at'         => now(),
        ]);
    }
}
