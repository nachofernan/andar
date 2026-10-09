<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla intermedia: qué disciplinas tiene cada usuario (profesional).
 * Un profesional puede tener varias disciplinas (o ninguna) y una disciplina varios profesionales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplina_user', function (Blueprint $table) {
            $table->id();
            // Si se borra la disciplina o el profesional, se borra también el vínculo (no el otro).
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['disciplina_id', 'user_id']); // La misma disciplina no se repite en un profesional.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplina_user');
    }
};
