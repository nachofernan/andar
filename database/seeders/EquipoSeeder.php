<?php

namespace Database\Seeders;

use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Carga el equipo de trabajo de ejemplo (los usuarios que pueden iniciar sesión).
 * Todos entran con la contraseña "password". Salvo Catalina, son personas inventadas.
 * Necesita que DisciplinaSeeder haya corrido antes.
 */
class EquipoSeeder extends Seeder
{
    public function run(): void
    {
        // Nombre, mail y disciplinas de cada uno. Puede tener varias, una o ninguna.
        $equipo = [
            // Catalina no se toca: el login viene precargado con su mail (app/Livewire/Login.php).
            ['Catalina Arnal', 'catalina@andar.test', ['Psicología']],
            ['Martín Ibáñez', 'martin@andar.test', ['Psicología']],
            ['Lucía Ferreyra', 'lucia@andar.test', ['Psicopedagogía', 'Psicología']], // Tiene dos.
            ['Paula Giménez', 'paula@andar.test', ['Fonoaudiología']],
            ['Sofía Maldonado', 'sofia@andar.test', ['Terapia Ocupacional']],
            ['Diego Acosta', 'diego@andar.test', ['Psicomotricidad']],
            ['Julieta Roldán', 'julieta@andar.test', ['Acompañamiento Terapéutico']],
            ['Romina Sosa', 'romina@andar.test', []], // Administración: no es de ninguna disciplina.
        ];

        foreach ($equipo as [$nombre, $email, $disciplinas]) {
            $usuario = User::create([
                'name' => $nombre,
                'email' => $email,
                'password' => 'password', // Se encripta sola (ver User::casts).
            ]);

            // Catalina es de Dirección (carga pacientes, usuarios y disciplinas); el resto, profesionales.
            $usuario->assignRole($email === 'catalina@andar.test' ? 'direccion' : 'profesional');

            // Se buscan por nombre para no depender del número de id.
            $usuario->disciplinas()->attach(Disciplina::whereIn('nombre', $disciplinas)->pluck('id'));
        }
    }
}
