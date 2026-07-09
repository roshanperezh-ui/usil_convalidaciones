<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulacion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulacion_id')->constrained('simulaciones')->cascadeOnDelete();
            $table->foreignId('curso_usil_id')->constrained('cursos_usil');
            $table->foreignId('curso_externo_id')->constrained('cursos_externos');
            $table->foreignId('equivalencia_id')->nullable()->constrained('equivalencias');
            $table->decimal('creditos_reconocidos', 4, 1);
            $table->boolean('excluido')->default(false);
            $table->enum('origen', ['automatico', 'manual', 'ia'])->default('automatico');
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulacion_detalle');
    }
};
