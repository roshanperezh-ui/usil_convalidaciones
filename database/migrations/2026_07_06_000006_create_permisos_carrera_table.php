<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RF-40: el coordinador solo ve/convalida sus carreras asignadas
        Schema::create('permisos_carrera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras');
            $table->timestamps();
            $table->unique(['usuario_id', 'carrera_id']); // índice único
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_carrera');
    }
};
