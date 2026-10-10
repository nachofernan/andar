<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el color de cada disciplina, en hexa (ej. #7c5cf0). Se usa para pintar sus etiquetas
     * en las pantallas. Todas nacen con el violeta de la app (ver Disciplina::COLOR_INICIAL),
     * también las que ya estaban cargadas.
     */
    public function up(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->string('color', 7)->default('#7c5cf0')->after('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
