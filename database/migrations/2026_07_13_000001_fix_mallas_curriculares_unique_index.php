<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-05: el índice único original (carrera_id, anio, version) no ignoraba
 * las filas con borrado lógico, así que una malla eliminada bloqueaba
 * permanentemente esa combinación para futuras altas.
 *
 * MySQL trata cada NULL como distinto dentro de un índice único, por lo
 * que no basta con agregar deleted_at a la clave (eso permitiría dos
 * mallas activas duplicadas, ambas con deleted_at NULL). Se usa una
 * columna generada que vale 1 solo si la fila está activa (deleted_at
 * NULL) y NULL si está eliminada, para que el índice único solo aplique
 * entre filas activas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            // El índice compuesto original también sostenía la FK de carrera_id; se
            // necesita un índice propio antes de poder eliminarlo.
            $table->index('carrera_id');
        });

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropUnique(['carrera_id', 'anio', 'version']);
            $table->boolean('activa_unica')
                ->nullable()
                ->virtualAs('IF(deleted_at IS NULL, 1, NULL)')
                ->after('deleted_at');
        });

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->unique(['carrera_id', 'anio', 'version', 'activa_unica']);
        });
    }

    public function down(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropUnique(['carrera_id', 'anio', 'version', 'activa_unica']);
            $table->dropColumn('activa_unica');
            $table->unique(['carrera_id', 'anio', 'version']);
            $table->dropIndex(['carrera_id']);
        });
    }
};
