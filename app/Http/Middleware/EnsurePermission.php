<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de acceso por PERMISO granular (RBAC).
 * Uso en rutas: ->middleware('permission:usuarios.gestionar')
 * Basta con tener UNO de los permisos indicados. El Superusuario siempre pasa.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'No autenticado.');
        }

        foreach ($permisos as $permiso) {
            if ($user->puede($permiso)) {
                return $next($request);
            }
        }

        abort(403, 'No tiene permisos para acceder a este recurso.');
    }
}
