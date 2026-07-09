<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facultades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_negocio_id')->constrained('unidades_negocio');
            $table->string('nombre', 150);
            $table->string('codigo', 20)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facultades');
    }
};
