<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla carreras representa el PROGRAMA ACADÉMICO. Se le añaden el grado
 * académico y el título profesional, además del borrado lógico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            $table->string('grado_academico', 100)->nullable()->after('nombre');
            $table->string('titulo_profesional', 150)->nullable()->after('grado_academico');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['grado_academico', 'titulo_profesional']);
        });
    }
};
