<?php

namespace Database\Seeders;

use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos iniciales para desarrollo (`php artisan migrate:fresh --seed`).
 * Carga las disciplinas del centro y un usuario para poder iniciar sesión.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        Disciplina::create(['nombre' => 'Psicopedagogía']);
        Disciplina::create(['nombre' => 'Fonoaudiología']);

        User::create([
            'name' => 'Catalina Arnal',
            'email' => 'catalina@andar.test',
            'password' => 'password', // Se encripta sola (ver User::casts).
            'disciplina_id' => $psicologia->id,
        ]);
    }
}
