<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->enum('accion', ['crear', 'editar', 'eliminar', 'restaurar', 'login', 'logout']);
            $table->string('tabla_afectada', 100);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip_origen', 45)->nullable();
            // Defecto corregido (MEJ / sección 7): antes "TIESTAMP" -> ahora TIMESTAMP
            $table->timestamp('created_at')->nullable();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_log');
    }
};
