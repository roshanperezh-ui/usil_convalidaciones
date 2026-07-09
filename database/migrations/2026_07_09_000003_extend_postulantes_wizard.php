<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte para el asistente de registro de postulante:
 *  - tipo_documento admite 'TEMP' (postulante sin documento → id temporal).
 *  - email pasa a ser opcional (permite guardar borrador).
 *  - tabla de documentos adjuntos del postulante.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE postulantes MODIFY tipo_documento ENUM('DNI','CE','PASAPORTE','PTP','TEMP') NOT NULL");
        DB::statement('ALTER TABLE postulantes MODIFY email VARCHAR(150) NULL');

        Schema::create('postulante_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')->constrained('postulantes')->cascadeOnDelete();
            $table->string('tipo', 60);                 // certificado, silabos, constancia, otros
            $table->string('nombre_original', 200);
            $table->string('ruta', 255);
            $table->unsignedInteger('tamano')->nullable();
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulante_documentos');
        DB::statement('ALTER TABLE postulantes MODIFY email VARCHAR(150) NOT NULL');
        DB::statement("ALTER TABLE postulantes MODIFY tipo_documento ENUM('DNI','CE','PASAPORTE','PTP') NOT NULL");
    }
};
