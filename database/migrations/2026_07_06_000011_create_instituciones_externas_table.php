<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones_externas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_id')->constrained('tipos_institucion');
            $table->string('nombre', 200);
            $table->string('pais', 100)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituciones_externas');
    }
};
