<?php

namespace Database\Seeders;

use App\Models\TipoInstitucion;
use Illuminate\Database\Seeder;

class TipoInstitucionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Universidad', 'Instituto'] as $nombre) {
            TipoInstitucion::updateOrCreate(['nombre' => $nombre]);
        }
    }
}
