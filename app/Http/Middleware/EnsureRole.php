<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de acceso por rol (RF-39 / RBAC).
 * Uso en rutas: ->middleware('role:Administrador')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->rol?->nombre, $roles, true)) {
            abort(403, 'No tiene permisos para acceder a este recurso.');
        }

        return $next($request);
    }
}
