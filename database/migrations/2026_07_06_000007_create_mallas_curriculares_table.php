<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mallas_curriculares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_id')->constrained('carreras');
            $table->year('anio');                                  // 2000-2100 (dominio en regla de negocio)
            $table->string('version', 20);
            $table->boolean('activa')->default(false);             // RN-02: solo una activa por carrera
            $table->enum('origen_carga', ['manual', 'excel']);
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->softDeletes();                                 // deleted_at
            $table->timestamps();
            // RN-01 y RN-03: versión única por carrera y año
            $table->unique(['carrera_id', 'anio', 'version']);
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mallas_curriculares');
    }
};
