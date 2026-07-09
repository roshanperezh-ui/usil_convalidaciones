<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado del flujo de revisión de equivalencias por postulante (CU-03, RF-18..23).
 * Permite la "Bandeja de Atención": pendiente → en_revision → aprobada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->enum('estado_equivalencias', ['pendiente', 'en_revision', 'aprobada'])
                ->default('pendiente')->after('estado');
            $table->foreignId('equivalencias_revisado_por')->nullable()
                ->after('estado_equivalencias')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('equivalencias_revisado_en')->nullable()
                ->after('equivalencias_revisado_por');
            $table->index('estado_equivalencias');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equivalencias_revisado_por');
            $table->dropColumn(['estado_equivalencias', 'equivalencias_revisado_en']);
        });
    }
};
