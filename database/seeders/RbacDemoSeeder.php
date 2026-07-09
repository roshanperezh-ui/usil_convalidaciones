<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de demostración para cada perfil RBAC (contraseña Demo#1234).
 * Asigna el alcance correspondiente (carrera al Director, facultad al Decano).
 */
class RbacDemoSeeder extends Seeder
{
    public function run(): void
    {
        $isi = Carrera::where('codigo', 'ISI')->first();

        $demo = [
            ['Servicios Académicos (demo)', 'servicios.demo@usil.edu.pe', Role::SERVICIOS],
            ['Director de Escuela (demo)',  'director.demo@usil.edu.pe',  Role::DIRECTOR],
            ['Decano (demo)',               'decano.demo@usil.edu.pe',    Role::DECANO],
            ['Auditor (demo)',              'auditor.demo@usil.edu.pe',   Role::AUDITOR],
            ['Consulta / Alta Dirección (demo)', 'consulta.demo@usil.edu.pe', Role::CONSULTA],
        ];

        foreach ($demo as [$nombre, $email, $rolNombre]) {
            $rol = Role::where('nombre', $rolNombre)->first();
            if (! $rol) {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'nombre'        => $nombre,
                    'password_hash' => Hash::make('Demo#1234'),
                    'rol_id'        => $rol->id,
                    'activo'        => true,
                    'primer_acceso' => false,
                ]
            );

            // Alcance: Director → carrera ISI; Decano → facultad de ISI.
            if ($rolNombre === Role::DIRECTOR && $isi) {
                $user->carrerasPermitidas()->syncWithoutDetaching([$isi->id]);
            } elseif ($rolNombre === Role::DECANO && $isi) {
                $user->facultadesPermitidas()->syncWithoutDetaching([$isi->facultad_id]);
            }
        }

        $this->command->info('Usuarios RBAC demo: servicios/director/decano/auditor/consulta (Demo#1234).');
    }
}
