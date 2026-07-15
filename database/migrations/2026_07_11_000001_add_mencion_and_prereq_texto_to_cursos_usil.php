<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte para importación de mallas desde Excel:
 *  - mencion:            nombre de la mención/especialidad a la que pertenece el
 *                        curso (null = curso del plan regular). Permite distinguir
 *                        en la lista los cursos de mención de los de ciclos regulares.
 *  - prerequisito_texto: prerrequisito tal como viene en el archivo (texto libre,
 *                        puede ser múltiple). No se pierde aunque no exista como
 *                        curso todavía; el prerequisito_id (FK) sigue siendo opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->string('mencion', 150)->nullable()->after('es_electivo');
            $table->string('prerequisito_texto', 255)->nullable()->after('prerequisito_id');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->dropColumn(['mencion', 'prerequisito_texto']);
        });
    }
};
