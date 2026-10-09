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
            // Se guarda cifrado (ver docs/cifrado.md), por eso es "text": el texto cifrado es mucho más largo que el DNI.
            // No lleva "unique" porque el mismo DNI cifrado dos veces da textos distintos: que no se repita lo controla el formulario.
            $table->text('dni');
            $table->date('fecha_nacimiento');
            $table->date('fecha_ingreso')->nullable(); // Cuándo empezó a venir al espacio.
            $table->string('obra_social')->nullable();
            $table->string('escuela')->nullable();
            $table->string('grado')->nullable();
            $table->text('diagnostico')->nullable(); // Se guarda cifrado (ver docs/cifrado.md).
            $table->boolean('activo')->default(true); // false = dado de baja, sin borrarlo.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
