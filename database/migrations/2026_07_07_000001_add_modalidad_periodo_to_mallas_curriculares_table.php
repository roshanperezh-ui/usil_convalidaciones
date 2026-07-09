<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            // Modalidad de dictado del plan.
            $table->enum('modalidad', ['presencial', 'hibrido', 'virtual'])
                ->default('presencial')->after('version');
            // Periodo académico de vigencia (formato AAAA-NN, p. ej. 2024-01).
            $table->string('periodo', 10)->nullable()->after('modalidad');
        });
    }

    public function down(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropColumn(['modalidad', 'periodo']);
        });
    }
};
