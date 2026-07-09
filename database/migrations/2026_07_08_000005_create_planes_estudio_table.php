<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan de Estudios: pertenece a un Programa Académico (carrera) y a una
 * Modalidad. Es el nivel previo a la Malla Curricular en la estructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_estudio', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('carrera_id')->constrained('carreras');
            $table->foreignId('modalidad_id')->constrained('modalidades');
            $table->string('nombre', 150);
            $table->year('anio');
            $table->string('version', 20);
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
            // Un programa no repite año+versión.
            $table->unique(['carrera_id', 'anio', 'version']);
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_estudio');
    }
};
