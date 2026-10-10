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
        // Nombre, mail, disciplinas y teléfono de cada uno. Puede tener varias disciplinas, una o ninguna.
        // El teléfono es opcional: el que no lo tiene lleva null.
        $equipo = [
            // Catalina no se toca: el login viene precargado con su mail (app/Livewire/Login.php).
            ['Catalina Arnal', 'catalina@andar.test', ['Psicología'], '11 5550-1001'],
            ['Martín Ibáñez', 'martin@andar.test', ['Psicología'], '11 5550-1002'],
            ['Lucía Ferreyra', 'lucia@andar.test', ['Psicopedagogía', 'Psicología'], null], // Tiene dos.
            ['Paula Giménez', 'paula@andar.test', ['Fonoaudiología'], '11 5550-1004'],
            ['Sofía Maldonado', 'sofia@andar.test', ['Terapia Ocupacional'], null],
            ['Diego Acosta', 'diego@andar.test', ['Psicomotricidad'], '11 5550-1006'],
            ['Julieta Roldán', 'julieta@andar.test', ['Acompañamiento Terapéutico'], null],
            ['Romina Sosa', 'romina@andar.test', [], '11 5550-1008'], // Administración: no es de ninguna disciplina.
            ['Florencia Paz', 'florencia@andar.test', ['Psicología'], '11 5550-1009'],
            ['Mariana Peralta', 'mariana@andar.test', ['Psicología'], null],
            ['Agustín Molina', 'agustin@andar.test', ['Psicología', 'Acompañamiento Terapéutico'], '11 5550-1011'], // Tiene dos.
            ['Camila Herrera', 'camila@andar.test', ['Psicopedagogía'], '11 5550-1012'],
            ['Nicolás Vera', 'nicolas@andar.test', ['Psicopedagogía'], null],
            ['Micaela Ojeda', 'micaela@andar.test', ['Fonoaudiología'], '11 5550-1014'],
            ['Verónica Luna', 'veronica@andar.test', ['Fonoaudiología'], null],
            ['Gabriela Ríos', 'gabriela@andar.test', ['Terapia Ocupacional'], '11 5550-1016'],
            ['Federico Campos', 'federico@andar.test', ['Terapia Ocupacional', 'Psicomotricidad'], '11 5550-1017'], // Tiene dos.
            ['Carolina Medina', 'carolina@andar.test', ['Psicomotricidad'], null],
            ['Ezequiel Navarro', 'ezequiel@andar.test', ['Psicomotricidad'], '11 5550-1019'],
            ['Belén Cabrera', 'belen@andar.test', ['Acompañamiento Terapéutico'], '11 5550-1020'],
            ['Tamara Quiroga', 'tamara@andar.test', ['Acompañamiento Terapéutico'], null],
        ];

        foreach ($equipo as [$nombre, $email, $disciplinas, $telefono]) {
            $usuario = User::create([
                'name' => $nombre,
                'email' => $email,
                'telefono' => $telefono,
                'password' => 'password', // Se encripta sola (ver User::casts).
            ]);

            // Catalina es de Dirección (carga pacientes, usuarios y disciplinas); el resto, profesionales.
            $usuario->assignRole($email === 'catalina@andar.test' ? 'direccion' : 'profesional');

            // Se buscan por nombre para no depender del número de id.
            $usuario->disciplinas()->attach(Disciplina::whereIn('nombre', $disciplinas)->pluck('id'));
        }
    }
}
