<?php

namespace Database\Seeders;

use App\Models\Disciplina;
use Illuminate\Database\Seeder;

/**
 * Carga las disciplinas de ejemplo del centro.
 * Va primero: el equipo (EquipoSeeder) necesita que ya existan.
 */
class DisciplinaSeeder extends Seeder
{
    public function run(): void
    {
        // Nombre y color (en hexa) de cada una. Los colores son los cinco del logo de Andar;
        // como las disciplinas son seis, la última lleva un rosa que no está en el logo.
        $disciplinas = [
            'Psicología' => '#6F5BCB', // Violeta.
            'Psicopedagogía' => '#70C2E4', // Celeste.
            'Fonoaudiología' => '#F76843', // Naranja.
            'Terapia Ocupacional' => '#3FB956', // Verde.
            'Psicomotricidad' => '#EDAE00', // Amarillo.
            'Acompañamiento Terapéutico' => '#E26A9B', // Rosa (fuera de la paleta del logo).
        ];

        foreach ($disciplinas as $nombre => $color) {
            Disciplina::create(['nombre' => $nombre, 'color' => $color]);
        }
    }
}
