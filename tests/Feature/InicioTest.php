<?php

namespace Tests\Feature;

use App\Livewire\Inicio;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas del Inicio como "mis pacientes" (quién ve qué paciente y el filtro de activos/inactivos).
 * El aviso de mensajes sin leer de cada paciente se prueba en MensajesSinLeerTest.
 */
class InicioTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /** Crea un paciente directo en la base (no hay factory de pacientes). Cada uno necesita un DNI distinto. */
    private function crearPaciente(array $datos = []): Paciente
    {
        return Paciente::create(array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
        ], $datos))->refresh();
    }

    public function test_un_profesional_ve_solo_los_pacientes_de_su_equipo(): void
    {
        $ana = $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304']);
        $bruno = $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222']);
        $this->crearPaciente(['nombre' => 'Carla', 'apellido' => 'Molina', 'dni' => '45333444']); // Sin equipo.
        $lucia = User::factory()->create();
        $ana->profesionales()->attach($lucia->id);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno')
            ->assertDontSee('Molina, Carla');

        // A Bruno lo deja afuera también si lo atiende otra persona.
        $bruno->profesionales()->attach(User::factory()->create()->id);
        Livewire::actingAs($lucia)->test(Inicio::class)->assertDontSee('Arnal, Bruno');
    }

    public function test_direccion_tambien_ve_solo_los_pacientes_que_atiende(): void
    {
        $ana = $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304']);
        $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222']); // No la atiende.
        $direccion = User::factory()->direccion()->create();
        $ana->profesionales()->attach($direccion->id);

        Livewire::actingAs($direccion)
            ->test(Inicio::class)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno');
    }

    public function test_por_defecto_no_muestra_los_pacientes_dados_de_baja(): void
    {
        $activo = $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304', 'activo' => true]);
        $inactivo = $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222', 'activo' => false]);
        $lucia = User::factory()->create();
        $activo->profesionales()->attach($lucia->id);
        $inactivo->profesionales()->attach($lucia->id);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno');
    }

    public function test_el_toggle_de_inactivos_los_suma_a_la_lista(): void
    {
        $activo = $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304', 'activo' => true]);
        $inactivo = $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222', 'activo' => false]);
        $lucia = User::factory()->create();
        $activo->profesionales()->attach($lucia->id);
        $inactivo->profesionales()->attach($lucia->id);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('verInactivos', true)
            ->assertSee('Zapata, Ana')
            ->assertSee('Arnal, Bruno')
            ->assertSee('Inactivo');
    }

    public function test_sin_pacientes_asignados_avisa_que_no_tiene(): void
    {
        $this->crearPaciente(); // Hay un paciente cargado, pero no es suyo.

        Livewire::actingAs(User::factory()->create())
            ->test(Inicio::class)
            ->assertSee('Todavía no tenés pacientes asignados.')
            ->assertDontSee('Zapata, Ana');
    }
}
