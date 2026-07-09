<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de aprobación por destino (Servicios → Coordinador → Director → Decano).
 * Aditivo: agrega el coordinador asignado y amplía el enum de estado
 * conservando los valores existentes (pendiente/en_revision/aprobada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulante_destinos', function (Blueprint $table) {
            $table->foreignId('asignado_a_id')->nullable()->after('carrera_id')
                ->constrained('usuarios')->nullOnDelete();
            $table->string('observacion_flujo', 300)->nullable()->after('equivalencias_revisado_en');
        });

        // Amplía el enum sin perder los valores actuales.
        DB::statement("ALTER TABLE postulante_destinos MODIFY COLUMN estado_equivalencias
            ENUM('pendiente','asignada','en_revision','observada','devuelta','aprobada')
            NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::statement("UPDATE postulante_destinos SET estado_equivalencias='pendiente'
            WHERE estado_equivalencias IN ('asignada','observada','devuelta')");
        DB::statement("ALTER TABLE postulante_destinos MODIFY COLUMN estado_equivalencias
            ENUM('pendiente','en_revision','aprobada') NOT NULL DEFAULT 'pendiente'");

        Schema::table('postulante_destinos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asignado_a_id');
            $table->dropColumn('observacion_flujo');
        });
    }
};
