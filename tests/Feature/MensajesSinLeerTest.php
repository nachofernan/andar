<?php

namespace Tests\Feature;

use App\Livewire\Inicio;
use App\Livewire\Pacientes\Lista;
use App\Livewire\Pacientes\Mensajes;
use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de los avisos de mensajes sin leer.
 * Un mensaje está sin leer si es de un paciente de mi equipo, lo escribió otra persona
 * y es posterior a la última vez que abrí ese historial (o nunca lo abrí).
 */
class MensajesSinLeerTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    // Así sale en el HTML el globito con la cantidad (en el menú y en la lista de pacientes).
    private const GLOBITO = 'title="Mensajes sin leer">';

    /** Crea un paciente directo en la base (no hay factory de pacientes). Cada uno necesita un DNI distinto. */
    private function crearPaciente(string $nombre = 'Ana', string $apellido = 'Zapata', string $dni = '52118304'): Paciente
    {
        return Paciente::create([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'fecha_nacimiento' => '2018-10-08',
        ])->refresh();
    }

    /** Crea un profesional y lo suma al equipo del paciente. */
    private function crearProfesionalDelEquipo(Paciente $paciente, string $nombre = 'Lucía Pérez'): User
    {
        $profesional = User::factory()->create(['name' => $nombre]);
        $paciente->profesionales()->attach($profesional->id);

        return $profesional;
    }

    /**
     * Guarda un mensaje directo en la base, sin pasar por el formulario.
     * $fecha permite fijar cuándo se envió (si no se pasa, queda la hora actual).
     */
    private function crearMensaje(Paciente $paciente, User $autor, string $cuerpo = 'Un mensaje.', ?string $fecha = null): Mensaje
    {
        $mensaje = new Mensaje([
            'paciente_id' => $paciente->id,
            'user_id' => $autor->id,
            'tipo' => 'mensaje',
            'cuerpo' => $cuerpo,
        ]);

        // Las fechas no se pueden pasar en el array de arriba: se asignan aparte.
        if ($fecha !== null) {
            $mensaje->created_at = $fecha;
            $mensaje->updated_at = $fecha;
        }

        $mensaje->save();

        return $mensaje;
    }

    /** Anota hasta cuándo vio el profesional el historial del paciente, como si lo hubiera abierto en esa fecha. */
    private function marcarVistoHasta(Paciente $paciente, User $profesional, string $fecha): void
    {
        $paciente->profesionales()->updateExistingPivot($profesional->id, ['mensajes_vistos_at' => $fecha]);
    }

    /** Hasta cuándo vio el profesional el historial del paciente, leído directo de la base (null = nunca lo abrió). */
    private function vistoHasta(Paciente $paciente, User $profesional): ?string
    {
        return $paciente->profesionales()->whereKey($profesional->id)->first()->pivot->mensajes_vistos_at;
    }

    // ---------------------------------------------------------------- Conteo (User::mensajesSinLeer)

    public function test_sin_haber_abierto_el_historial_cuentan_todos_los_mensajes_de_otros_y_ninguno_propio(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $this->crearMensaje($paciente, $marta);
        $this->crearMensaje($paciente, $marta);
        $this->crearMensaje($paciente, $lucia); // El propio no cuenta.

        $this->assertSame([$paciente->id => 2], $lucia->mensajesSinLeer()->all());
    }

    public function test_no_cuentan_los_mensajes_de_pacientes_que_no_son_de_mi_equipo(): void
    {
        $paciente = $this->crearPaciente();
        $ajeno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $ajeno->profesionales()->attach($marta->id); // A Bruno lo atiende solo Marta.
        $this->crearMensaje($paciente, $marta);
        $this->crearMensaje($ajeno, $marta);

        // Solo aparece el paciente de su equipo; el otro ni figura en la lista.
        $this->assertSame([$paciente->id => 1], $lucia->mensajesSinLeer()->all());
    }

    public function test_solo_cuentan_los_mensajes_posteriores_a_la_ultima_vez_que_abri_el_historial(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $this->crearMensaje($paciente, $marta, 'Anterior.', '2026-10-01 10:00:00');
        $this->marcarVistoHasta($paciente, $lucia, '2026-10-02 12:00:00');
        $this->crearMensaje($paciente, $marta, 'Posterior.', '2026-10-03 10:00:00');

        $this->assertSame([$paciente->id => 1], $lucia->mensajesSinLeer()->all());
    }

    // ---------------------------------------------------------------- Marcar como leído

    public function test_abrir_el_historial_siendo_del_equipo_deja_ese_paciente_sin_mensajes_sin_leer(): void
    {
        $paciente = $this->crearPaciente();
        $otro = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $otro->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($paciente, $marta);
        $this->crearMensaje($otro, $marta);

        Livewire::actingAs($lucia)->test(Mensajes::class, ['paciente' => $paciente]);

        // Se marca como leído solo el paciente que abrió: el otro sigue pendiente.
        $this->assertSame([$otro->id => 1], $lucia->mensajesSinLeer()->all());
        // Y a Marta no se le toca nada: cada uno tiene su propia marca.
        $this->assertNull($this->vistoHasta($paciente, $marta));
    }

    public function test_abrir_la_ficha_siendo_de_direccion_pero_no_del_equipo_no_modifica_nada(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $this->crearMensaje($paciente, $marta);
        // Dirección puede abrir la ficha de cualquier paciente, pero Pedro no atiende a este.
        // (Un profesional de afuera del equipo ni siquiera llega a abrirla: recibe 403. Ver RolesTest.)
        $pedro = User::factory()->direccion()->create();

        $this->actingAs($pedro)->get("/pacientes/{$paciente->id}")->assertOk();

        // No se lo sumó al equipo ni se marcó como leído el historial de los que sí lo son.
        $this->assertDatabaseCount('paciente_user', 2);
        $this->assertDatabaseMissing('paciente_user', ['user_id' => $pedro->id]);
        $this->assertNull($this->vistoHasta($paciente, $lucia));
        $this->assertSame([$paciente->id => 1], $lucia->mensajesSinLeer()->all());
    }

    public function test_enviar_un_mensaje_no_me_genera_aviso_a_mi_pero_si_al_resto_del_equipo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        $this->travelTo('2026-10-08 10:00:00');
        $historial = Livewire::actingAs($lucia)->test(Mensajes::class, ['paciente' => $paciente]);

        // Mientras Lucía tiene la ficha abierta, Marta escribe.
        $this->travelTo('2026-10-08 10:05:00');
        $this->crearMensaje($paciente, $marta, 'Llegó mientras Lucía escribía.');

        $this->travelTo('2026-10-08 10:10:00');
        $historial->set('cuerpo', 'Respuesta de Lucía.')->call('enviar')->assertHasNoErrors();

        // Quien escribe está mirando el historial: enviar también lo marca como leído, incluido lo de Marta.
        $this->assertSame([], $lucia->mensajesSinLeer()->all());
        // A Marta le queda el aviso del mensaje de Lucía.
        $this->assertSame([$paciente->id => 1], $marta->mensajesSinLeer()->all());
    }

    // ---------------------------------------------------------------- Etiqueta "Nuevo"

    public function test_la_etiqueta_nuevo_marca_solo_los_mensajes_de_otros_que_todavia_no_habia_visto(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $this->crearMensaje($paciente, $marta, 'Mensaje ya visto.', '2026-10-01 10:00:00');
        $this->marcarVistoHasta($paciente, $lucia, '2026-10-02 12:00:00');
        $this->crearMensaje($paciente, $marta, 'Mensaje sin ver.', '2026-10-03 10:00:00');
        $this->crearMensaje($paciente, $lucia, 'Mensaje propio.', '2026-10-04 10:00:00');

        $html = Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            // Se guarda lo que había visto ANTES de esta visita: de ahí sale qué es nuevo.
            ->assertSet('vistoHasta', '2026-10-02 12:00:00')
            // La etiqueta va en el encabezado de cada mensaje, o sea antes de su texto. El más nuevo va arriba.
            ->assertSeeInOrder(['Mensaje propio.', 'Nuevo', 'Mensaje sin ver.', 'Mensaje ya visto.'])
            ->html();

        // Una sola etiqueta: ni el ya visto ni el propio la llevan.
        $this->assertSame(1, substr_count($html, 'Nuevo'));
    }

    public function test_si_nunca_abri_el_historial_todos_los_mensajes_de_otros_llevan_la_etiqueta_nuevo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $this->crearMensaje($paciente, $marta, 'Primero de Marta.');
        $this->crearMensaje($paciente, $marta, 'Segundo de Marta.');
        $this->crearMensaje($paciente, $lucia, 'Uno de Lucía.');

        $html = Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSet('vistoHasta', null)
            ->html();

        // Los dos de Marta; el propio nunca es "Nuevo".
        $this->assertSame(2, substr_count($html, 'Nuevo'));
    }

    // ---------------------------------------------------------------- Inicio

    public function test_el_inicio_lista_los_pacientes_con_mensajes_sin_leer_y_su_cantidad(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $carla = $this->crearPaciente('Carla', 'Molina', '45333444');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $carla->profesionales()->attach([$lucia->id, $marta->id]); // Es de su equipo, pero no tiene mensajes.
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);

        $this->actingAs($lucia)
            ->get('/')
            ->assertOk()
            ->assertSeeLivewire(Inicio::class)
            // Un renglón por paciente, ordenados por apellido, cada uno con su cantidad al lado.
            ->assertSeeInOrder(['Mensajes sin leer', 'Arnal, Bruno', '>1</span>', 'Zapata, Ana', '>2</span>'], false)
            ->assertDontSee('Molina, Carla')
            ->assertDontSee('No tenés mensajes sin leer.');
    }

    public function test_el_inicio_avisa_cuando_no_hay_mensajes_sin_leer(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Inicio::class)
            ->assertSee('No tenés mensajes sin leer.');
    }

    // ---------------------------------------------------------------- Lista de pacientes

    public function test_la_lista_de_pacientes_muestra_el_globito_solo_en_los_que_tienen_mensajes_sin_leer(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);

        $html = Livewire::actingAs($lucia)
            ->test(Lista::class)
            // El globito va al lado del nombre de Ana, con su cantidad.
            ->assertSeeInOrder(['Arnal, Bruno', 'Zapata, Ana', self::GLOBITO.'2</span>'], false)
            ->html();

        // Bruno no tiene mensajes: hay un solo globito en toda la lista.
        $this->assertSame(1, substr_count($html, self::GLOBITO));
    }

    // ---------------------------------------------------------------- Menú

    public function test_el_menu_muestra_el_total_de_mensajes_sin_leer_de_todos_mis_pacientes(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);

        // Se pide el inicio, que no usa este globito (ahí las cantidades van sin el title): el único que puede aparecer es el del menú.
        $this->actingAs($lucia)
            ->get('/')
            ->assertOk()
            ->assertSee(self::GLOBITO.'3</span>', false);
    }

    public function test_el_menu_no_muestra_el_globito_cuando_no_hay_mensajes_sin_leer(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee(self::GLOBITO, false);
    }

    public function test_al_abrir_la_ficha_el_menu_de_esa_misma_pagina_ya_no_cuenta_los_mensajes_de_ese_paciente(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);

        // Antes de entrar tenía 3 (2 de Ana y 1 de Bruno). Al abrir la ficha de Ana, sus 2 pasan a leídos.
        // El menú de esa misma respuesta ya tiene que decir 1: si dijera 3, el número quedaría viejo
        // hasta cambiar de pantalla (el menú se habría armado antes de marcar como leído).
        $this->actingAs($lucia)
            ->get("/pacientes/{$ana->id}")
            ->assertOk()
            ->assertSee(self::GLOBITO.'1</span>', false)
            ->assertDontSee(self::GLOBITO.'3</span>', false);
    }
}
