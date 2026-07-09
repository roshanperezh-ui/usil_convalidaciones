<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista gestionable de cursos de ORIGEN que nunca se convalidan (por materia).
 * Cada fila es una palabra o frase clave que, si aparece en el nombre del curso
 * de origen, lo marca automáticamente como "no convalidable".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_no_convalidables', function (Blueprint $table) {
            $table->id();
            $table->string('palabra_clave', 120);       // texto tal como lo escribe el usuario
            $table->string('clave_normalizada', 120)->index(); // sin acentos/minúsculas, para comparar
            $table->string('motivo', 150)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_no_convalidables');
    }
};
