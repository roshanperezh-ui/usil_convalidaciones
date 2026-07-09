<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos adicionales del curso para la ficha de mantenimiento (F3):
 * tipo de curso, área, competencias y resultados de aprendizaje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->enum('tipo_curso', ['teorico', 'practico', 'teorico_practico'])->nullable()->after('horas_practica');
            $table->string('area', 100)->nullable()->after('tipo_curso');
            $table->json('competencias')->nullable()->after('area');
            $table->text('resultados_aprendizaje')->nullable()->after('competencias');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->dropColumn(['tipo_curso', 'area', 'competencias', 'resultados_aprendizaje']);
        });
    }
};
