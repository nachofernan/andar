<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de pacientes (los niños/as que se atienden en el centro).
 * Obligatorios: nombre, apellido, DNI y fecha de nacimiento. El resto es opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('dni')->unique(); // Texto y no número, para no perder ceros ni puntos.
            $table->date('fecha_nacimiento');
            $table->date('fecha_ingreso')->nullable(); // Cuándo empezó a venir al espacio.
            $table->string('obra_social')->nullable();
            $table->string('escuela')->nullable();
            $table->string('grado')->nullable();
            $table->text('diagnostico')->nullable();
            $table->boolean('activo')->default(true); // false = dado de baja, sin borrarlo.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
