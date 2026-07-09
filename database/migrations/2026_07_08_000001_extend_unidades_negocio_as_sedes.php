<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla unidades_negocio representa la SEDE/campus (tope de la estructura
 * institucional). Se le añaden código y dirección, y borrado lógico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->unique()->after('id');
            $table->string('direccion', 200)->nullable()->after('nombre');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'direccion']);
        });
    }
};
