<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_institucion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();   // 'Universidad', 'Instituto'
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_institucion');
    }
};
