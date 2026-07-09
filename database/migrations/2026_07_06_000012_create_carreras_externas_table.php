<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carreras_externas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones_externas');
            $table->string('nombre', 200);
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carreras_externas');
    }
};
