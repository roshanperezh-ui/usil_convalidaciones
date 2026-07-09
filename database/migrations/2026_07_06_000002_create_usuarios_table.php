<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('email', 150)->unique();              // correo institucional
            $table->string('password_hash', 255);                // bcrypt/argon2 (RNF-01)
            $table->foreignId('rol_id')->constrained('roles');   // RF-39
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('intentos_fallidos')->default(0); // RF-41
            $table->timestamp('bloqueado_hasta')->nullable();             // RF-41
            $table->boolean('primer_acceso')->default(true);              // RF-42
            $table->string('token_recuperacion', 255)->nullable();        // RF-41
            $table->timestamp('token_expira')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
