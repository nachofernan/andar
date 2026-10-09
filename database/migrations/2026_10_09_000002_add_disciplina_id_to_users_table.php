<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le agrega a cada usuario (profesional) su disciplina.
 * Es opcional: puede haber usuarios sin disciplina asignada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Si se borra la disciplina, el profesional no se borra: queda sin disciplina.
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplinas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disciplina_id');
        });
    }
};
