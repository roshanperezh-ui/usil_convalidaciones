<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nota 3FN: datos del estudiante desnormalizados de forma intencional
        // para preservar el dato histórico al momento de la simulación.
        Schema::create('simulaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->enum('tipo_documento', ['DNI', 'CE', 'PASAPORTE']);
            $table->string('numero_documento', 20);
            $table->string('email', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('ciclo_postulacion', 10);   // AAAA-N
            $table->foreignId('carrera_externa_id')->constrained('carreras_externas');
            $table->foreignId('carrera_usil_id')->constrained('carreras');
            $table->foreignId('malla_usil_id')->constrained('mallas_curriculares');
            $table->enum('estado', ['borrador', 'generada', 'enviada', 'aceptada', 'desistida'])->default('borrador');
            $table->string('pdf_path', 500)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulaciones');
    }
};
