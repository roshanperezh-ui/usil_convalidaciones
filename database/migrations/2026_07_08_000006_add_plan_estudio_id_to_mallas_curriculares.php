<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza la Malla Curricular con su Plan de Estudios (nivel superior de la
 * estructura institucional). Nullable para no romper las mallas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->foreignId('plan_estudio_id')->nullable()->after('carrera_id')
                ->constrained('planes_estudio')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_estudio_id');
        });
    }
};
