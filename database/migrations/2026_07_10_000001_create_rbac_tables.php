<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capa RBAC granular (aditiva):
 *  - permisos: catálogo de acciones por módulo.
 *  - rol_permiso: qué permisos tiene cada rol.
 *  - permisos_facultad: alcance por facultad (para el Decano).
 * Renombra los 2 roles existentes a la nueva nomenclatura conservando su id
 * (y por tanto los usuarios ya asignados). No elimina ni altera datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();      // p.ej. 'solicitudes.asignar'
            $table->string('modulo', 40);               // agrupador para la UI
            $table->string('descripcion', 150)->nullable();
            $table->timestamps();
            $table->engine = 'InnoDB';
        });

        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
            $table->primary(['rol_id', 'permiso_id']);
            $table->engine = 'InnoDB';
        });

        // Alcance por facultad (Decano ve todas las carreras de su facultad).
        Schema::create('permisos_facultad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('facultad_id')->constrained('facultades')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['usuario_id', 'facultad_id']);
            $table->engine = 'InnoDB';
        });

        // Renombrado no destructivo de los roles existentes (conserva id → usuarios intactos).
        DB::table('roles')->where('nombre', 'Administrador')->update(['nombre' => 'Superusuario']);
        DB::table('roles')->where('nombre', 'Coordinador')->update(['nombre' => 'Coordinador de Carrera']);
    }

    public function down(): void
    {
        DB::table('roles')->where('nombre', 'Superusuario')->update(['nombre' => 'Administrador']);
        DB::table('roles')->where('nombre', 'Coordinador de Carrera')->update(['nombre' => 'Coordinador']);
        Schema::dropIfExists('permisos_facultad');
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permisos');
    }
};
