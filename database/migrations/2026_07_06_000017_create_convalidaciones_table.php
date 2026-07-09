<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convalidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulacion_id')->unique()->constrained('simulaciones'); // 1:1
            $table->date('fecha_confirmacion');
            $table->string('memorandum_numero', 50)->nullable();
            $table->string('memorandum_pdf_path', 500)->nullable();
            $table->enum('estado', ['confirmada', 'anulada'])->default('confirmada'); // RF-46
            $table->string('motivo_anulacion', 300)->nullable();                       // RF-46
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convalidaciones');
    }
};
