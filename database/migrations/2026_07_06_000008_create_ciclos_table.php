<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciclos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('malla_id')->constrained('mallas_curriculares')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');   // 1-14
            $table->string('nombre', 50)->nullable();
            $table->timestamps();
            $table->unique(['malla_id', 'numero']);
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciclos');
    }
};
