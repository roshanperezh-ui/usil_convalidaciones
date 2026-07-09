<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca por curso USIL si es convalidable. Los cursos no convalidables
 * (p. ej. Inglés de cualquier nivel, Proyecto para computación I/II) no se
 * ofrecen como destino en el mapeo de convalidación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->boolean('convalidable')->default(true)->after('es_electivo');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->dropColumn('convalidable');
        });
    }
};
