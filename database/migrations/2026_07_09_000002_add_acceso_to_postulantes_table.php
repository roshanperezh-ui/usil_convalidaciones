<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso del postulante al portal de seguimiento (login con su correo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->string('password_hash')->nullable()->after('email');
            $table->boolean('acceso_habilitado')->default(true)->after('password_hash');
            $table->timestamp('ultimo_acceso')->nullable()->after('acceso_habilitado');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn(['password_hash', 'acceso_habilitado', 'ultimo_acceso']);
        });
    }
};
