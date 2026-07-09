<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * RF-40: el coordinador solo ve/convalida sus carreras asignadas.
 * El administrador ve todo. Aplica sobre modelos con columna carrera_*_id.
 */
trait FiltraPorCarrera
{
    public function scopeVisiblePara(Builder $query, User $user, string $columna = 'carrera_usil_id'): Builder
    {
        if ($user->esAdministrador()) {
            return $query;
        }

        $ids = $user->carrerasPermitidas()->pluck('carreras.id');

        return $query->whereIn($columna, $ids);
    }
}
