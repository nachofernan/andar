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
        $nombres = [
            'Psicología',
            'Psicopedagogía',
            'Fonoaudiología',
            'Terapia Ocupacional',
            'Psicomotricidad',
            'Acompañamiento Terapéutico',
        ];

        foreach ($nombres as $nombre) {
            Disciplina::create(['nombre' => $nombre]);
        }
    }
}
