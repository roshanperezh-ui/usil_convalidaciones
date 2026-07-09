<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postulante (solicitante de convalidación por traslado externo).
 * Diseño normalizado (3FN), documento único, estado de proceso, FKs a la
 * estructura existente, borrado lógico y trazabilidad (RNF-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulantes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();                       // código de postulante (trazabilidad)

            // --- Identificación ---
            $table->enum('tipo_documento', ['DNI', 'CE', 'PASAPORTE', 'PTP']);
            $table->string('numero_documento', 20);
            $table->string('nombres', 100);
            $table->string('apellido_paterno', 100);
            $table->string('apellido_materno', 100)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('sexo', ['masculino', 'femenino', 'otro', 'no_especifica'])->nullable();
            $table->string('nacionalidad', 60)->default('Peruana');

            // --- Contacto ---
            $table->string('email', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('pais_residencia', 60)->nullable();
            $table->string('direccion', 200)->nullable();

            // --- Procedencia y destino (estructura existente) ---
            $table->foreignId('institucion_origen_id')->nullable()->constrained('instituciones_externas')->nullOnDelete();
            $table->foreignId('carrera_externa_id')->nullable()->constrained('carreras_externas')->nullOnDelete();
            $table->foreignId('carrera_destino_id')->nullable()->constrained('carreras')->nullOnDelete();
            $table->string('ciclo_postulacion', 7)->nullable();           // AAAA-N

            // --- Proceso ---
            $table->enum('estado', ['nuevo', 'en_evaluacion', 'admitido', 'rechazado', 'matriculado'])->default('nuevo');
            $table->text('observaciones')->nullable();

            // --- Trazabilidad ---
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            // RN: un documento identifica a un único postulante vigente.
            $table->unique(['tipo_documento', 'numero_documento'], 'uq_postulante_documento');
            $table->index('estado');
            $table->index('email');
            $table->engine = 'InnoDB';
        });

        // Integración opcional: una simulación puede originarse de un postulante.
        Schema::table('simulaciones', function (Blueprint $table) {
            $table->foreignId('postulante_id')->nullable()->after('id')
                ->constrained('postulantes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('simulaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('postulante_id');
        });
        Schema::dropIfExists('postulantes');
    }
};
