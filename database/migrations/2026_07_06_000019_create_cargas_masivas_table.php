<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RF-11: estado de progreso; RF-12: logs de éxito/fallo por registro.
        Schema::create('cargas_masivas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('malla_id')->nullable()->constrained('mallas_curriculares');
            $table->string('archivo', 255);
            $table->enum('estado', ['pendiente', 'procesando', 'completado', 'fallido'])->default('pendiente');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('procesados')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->json('detalle_errores')->nullable(); // [{linea, mensaje}]
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_masivas');
    }
};
