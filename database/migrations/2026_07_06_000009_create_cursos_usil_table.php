<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_usil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('ciclos')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 200);
            $table->decimal('creditos', 4, 1);            // > 0 y <= 30
            $table->decimal('horas_teoria', 4, 1)->nullable();
            $table->decimal('horas_practica', 4, 1)->nullable();
            $table->boolean('es_electivo')->default(false);
            $table->foreignId('prerequisito_id')->nullable()->constrained('cursos_usil'); // autorreferencia
            $table->text('silabo_texto')->nullable();     // descripción para IA
            $table->softDeletes();
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_usil');
    }
};
