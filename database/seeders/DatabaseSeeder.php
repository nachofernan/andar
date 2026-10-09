<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo para desarrollo (`php artisan migrate:fresh --seed`).
 * No carga nada por sí mismo: llama a los seeders de cada tema.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // El orden importa: el equipo necesita las disciplinas, los pacientes al equipo, y los mensajes a los pacientes.
        $this->call([
            DisciplinaSeeder::class,
            EquipoSeeder::class,
            PacienteSeeder::class,
            MensajeSeeder::class,
        ]);
    }
}
