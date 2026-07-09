<?php

namespace App\Services;

/**
 * RNF-09 (Ley N.º 29733): elimina datos personales del texto antes de
 * enviarlo a un servicio externo de IA. Solo se transmite contenido académico.
 */
class Seudonimizador
{
    public static function limpiar(?string $texto): string
    {
        if (! $texto) {
            return '';
        }

        // Correos electrónicos.
        $texto = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[correo]', $texto);
        // Documentos (DNI 8 dígitos, CE/pasaporte alfanumérico largo).
        $texto = preg_replace('/\b\d{8,12}\b/', '[documento]', $texto);
        // Teléfonos.
        $texto = preg_replace('/\b9\d{8}\b/', '[telefono]', $texto);

        return trim($texto);
    }
}
