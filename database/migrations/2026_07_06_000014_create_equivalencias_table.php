<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equivalencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_externa_id')->constrained('carreras_externas');
            $table->foreignId('carrera_usil_id')->constrained('carreras');
            $table->foreignId('curso_externo_id')->constrained('cursos_externos');
            $table->foreignId('curso_usil_id')->constrained('cursos_usil');
            $table->enum('tipo_equivalencia', ['completa', 'parcial'])->default('completa');
            $table->decimal('confianza_ia', 5, 2)->nullable();              // 0-100
            $table->enum('origen', ['manual', 'ia', 'precargada'])->default('manual');
            $table->foreignId('usuario_id')->constrained('usuarios');       // coordinador que aprobó
            $table->softDeletes();
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equivalencias');
    }
};
