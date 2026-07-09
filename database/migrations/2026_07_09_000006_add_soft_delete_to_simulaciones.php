<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite eliminar (lógicamente) una simulación conservando el registro y el
 * motivo de la eliminación en la base de datos (trazabilidad / auditoría).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulaciones', function (Blueprint $table) {
            $table->string('motivo_eliminacion', 300)->nullable()->after('observaciones');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('simulaciones', function (Blueprint $table) {
            $table->dropColumn(['motivo_eliminacion', 'deleted_at']);
        });
    }
};
