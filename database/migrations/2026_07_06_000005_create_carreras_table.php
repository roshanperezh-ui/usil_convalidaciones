<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carreras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facultad_id')->constrained('facultades');
            $table->string('nombre', 150);
            $table->string('codigo', 20)->unique();
            $table->unsignedTinyInteger('max_ciclos')->default(10); // 10 o 14 (Medicina)
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carreras');
    }
};
