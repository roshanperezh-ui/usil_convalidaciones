<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El workspace de simulación marca las filas emparejadas con "Reutilizar
 * equivalencias del catálogo" como origen 'catalogo', pero el enum no lo
 * incluía y el guardado fallaba con 422.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE simulacion_detalle MODIFY origen ENUM('automatico','manual','ia','similitud','catalogo') NOT NULL DEFAULT 'automatico'"
        );
    }

    public function down(): void
    {
        DB::table('simulacion_detalle')->where('origen', 'catalogo')->update(['origen' => 'manual']);
        DB::statement(
            "ALTER TABLE simulacion_detalle MODIFY origen ENUM('automatico','manual','ia','similitud') NOT NULL DEFAULT 'automatico'"
        );
    }
};
