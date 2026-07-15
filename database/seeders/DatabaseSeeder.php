<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TipoInstitucionSeeder::class,
            AdminUserSeeder::class,
            DemoUsersSeeder::class, // Cuentas demo del login (una por perfil, Demo#1234).
            EstructuraSeeder::class, // Sedes y modalidades base.
            UsilPregradoSeeder::class, // Facultades y programas académicos reales de USIL.
        ]);
    }
}
