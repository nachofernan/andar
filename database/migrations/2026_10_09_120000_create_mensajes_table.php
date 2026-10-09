<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de mensajes: el historial de comunicación del equipo sobre cada paciente.
 * Cada fila es un mensaje que un profesional escribió en la ficha de un paciente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            // "constrained" a secas: la base no deja borrar un paciente o un usuario que tenga mensajes.
            $table->foreignId('paciente_id')->constrained();
            $table->foreignId('user_id')->constrained(); // Quién lo escribió.
            $table->string('tipo'); // mensaje, informe, indicacion o nota (ver Mensaje::TIPOS).
            $table->text('cuerpo'); // El texto. Se guarda cifrado (ver docs/cifrado.md).
            $table->timestamps(); // created_at es la fecha y hora de envío.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
