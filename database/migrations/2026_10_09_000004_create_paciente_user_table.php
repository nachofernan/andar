<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla intermedia: qué profesionales atienden a qué pacientes (el "equipo" del paciente).
 * Un paciente puede tener varios profesionales y un profesional varios pacientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_user', function (Blueprint $table) {
            $table->id();
            // Si se borra el paciente o el profesional, se borra también el vínculo.
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['paciente_id', 'user_id']); // El mismo profesional no se repite en un paciente.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_user');
    }
};
