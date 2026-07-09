<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El sistema es de convalidación, no de grados/títulos: se eliminan los campos
 * grado_academico y titulo_profesional del Programa Académico (carreras).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            $table->dropColumn(['grado_academico', 'titulo_profesional']);
        });
    }

    public function down(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            $table->string('grado_academico', 100)->nullable()->after('nombre');
            $table->string('titulo_profesional', 150)->nullable()->after('grado_academico');
        });
    }
};
