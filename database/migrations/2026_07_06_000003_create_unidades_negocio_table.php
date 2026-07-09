<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_negocio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();   // 'USIL Lima', 'USIL Arequipa'
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_negocio');
    }
};
