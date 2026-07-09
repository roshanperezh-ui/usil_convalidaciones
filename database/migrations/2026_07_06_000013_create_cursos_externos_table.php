<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_externos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_externa_id')->constrained('carreras_externas');
            $table->string('codigo', 30)->nullable();
            $table->string('nombre', 200);
            $table->decimal('creditos', 4, 1)->nullable();
            $table->text('silabo_texto')->nullable();   // para IA
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_externos');
    }
};
