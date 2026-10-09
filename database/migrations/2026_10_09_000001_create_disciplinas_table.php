<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de disciplinas del centro (Psicología, Psicopedagogía, Fonoaudiología, etc.).
 * Cada profesional pertenece a una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique(); // No puede haber dos disciplinas con el mismo nombre.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinas');
    }
};
