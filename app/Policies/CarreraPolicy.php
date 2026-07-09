<?php

namespace App\Policies;

use App\Models\Carrera;
use App\Models\User;

class CarreraPolicy
{
    public function before(User $user): ?bool
    {
        // El administrador tiene acceso total.
        return $user->esAdministrador() ? true : null;
    }

    public function ver(User $user, Carrera $carrera): bool
    {
        return $user->carrerasPermitidas()->whereKey($carrera->id)->exists();
    }

    public function gestionar(User $user, Carrera $carrera): bool
    {
        return $this->ver($user, $carrera);
    }
}
