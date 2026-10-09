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
            // Hasta cuándo vio este profesional el historial de mensajes del paciente. Vacío = nunca lo abrió.
            // Sirve para saber qué mensajes tiene sin leer (ver docs/mensajes.md).
            $table->timestamp('mensajes_vistos_at')->nullable();
            $table->timestamps();

            $table->unique(['paciente_id', 'user_id']); // El mismo profesional no se repite en un paciente.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_user');
    }
};
