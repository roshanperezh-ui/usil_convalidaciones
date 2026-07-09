<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El campo 'sexo' del postulante pasa a llamarse 'genero'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE postulantes CHANGE sexo genero ENUM('masculino','femenino','otro','no_especifica') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE postulantes CHANGE genero sexo ENUM('masculino','femenino','otro','no_especifica') NULL");
    }
};
