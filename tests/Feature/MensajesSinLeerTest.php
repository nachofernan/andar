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

    // Así sale en el HTML el puntito rojo del menú, en el link "Mis pacientes". No lleva número: solo avisa que hay algo.
    private const PUNTITO = 'title="Tenés mensajes sin leer"';

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

    public function test_no_cuentan_los_mensajes_de_pacientes_inactivos(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);
        $this->crearMensaje($bruno, $marta);
        $bruno->update(['activo' => false]); // Se lo da de baja con mensajes que Lucía nunca vio.

        // Bruno sigue siendo de su equipo, pero dado de baja ni figura en la lista.
        $this->assertSame([$ana->id => 1], $lucia->mensajesSinLeer()->all());

        // No se marcaron como leídos, solo se dejan de avisar: si se lo reactiva, vuelven a contar.
        $bruno->update(['activo' => true]);
        $this->assertEquals([$ana->id => 1, $bruno->id => 2], $lucia->mensajesSinLeer()->all());
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
            // El historial va del más viejo al más nuevo, y la etiqueta está en el encabezado de su mensaje
            // (antes del texto): por eso queda entre el ya visto y el que no se había visto.
            ->assertSeeInOrder(['Mensaje ya visto.', 'Nuevo', 'Mensaje sin ver.', 'Mensaje propio.'])
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

    // ---------------------------------------------------------------- Inicio ("mis pacientes")

    public function test_el_inicio_lista_todos_mis_pacientes_con_la_cantidad_de_sin_leer_de_cada_uno(): void
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

        $respuesta = $this->actingAs($lucia)
            ->get('/')
            ->assertOk()
            ->assertSeeLivewire(Inicio::class)
            // Un renglón por cada paciente de su equipo, con su cantidad escrita al lado si tiene
            // ("mensaje" en singular cuando es uno solo).
            // Arriba van los que tienen mensajes sin leer (por apellido) y después el resto: por eso Carla queda última.
            ->assertSeeInOrder([
                // Arriba, el saludo con el total: 3 mensajes de 2 pacientes.
                'Tenés', '>3 mensajes sin leer</span>', 'de 2 pacientes.',
                'Mis pacientes',
                'Arnal, Bruno', '>1 mensaje sin leer</span>',
                'Zapata, Ana', '>2 mensajes sin leer</span>',
                'Molina, Carla',
            ], false)
            ->assertDontSee('Todavía no tenés pacientes asignados.');

        // Tres avisos: el del saludo y uno por paciente con mensajes. Carla, que no tiene, no lleva ninguno
        // (ni un "0 mensajes sin leer").
        $this->assertSame(3, substr_count($respuesta->getContent(), ' sin leer</span>'));
    }

    public function test_el_inicio_avisa_cuando_no_tengo_pacientes_asignados(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Inicio::class)
            ->assertSee('Todavía no tenés pacientes asignados.');
    }

    public function test_los_mensajes_de_un_paciente_inactivo_no_suman_en_el_saludo_del_inicio(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);
        $bruno->update(['activo' => false]);

        // Con el de Bruno serían "3 mensajes sin leer de 2 pacientes": dado de baja, no suma ni como mensaje ni como paciente.
        $html = Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSeeInOrder(['Tenés', '>2 mensajes sin leer</span>', 'de 1 paciente.'], false)
            // Aunque se lo traiga a la tabla con el filtro de estado, su renglón no lleva aviso.
            ->set('estado', 'todos')
            ->assertSee('Arnal, Bruno')
            ->html();

        // Dos avisos: el del saludo y el del renglón de Ana.
        $this->assertSame(2, substr_count($html, ' sin leer</span>'));
    }

    public function test_si_lo_unico_sin_leer_es_de_un_paciente_inactivo_el_inicio_dice_que_no_hay_mensajes_nuevos(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($bruno, $marta);
        $bruno->update(['activo' => false]);

        $html = Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSee('No tenés mensajes nuevos.')
            ->html();

        // Ni en el saludo ni en la tabla queda un aviso.
        $this->assertStringNotContainsString('sin leer</span>', $html);
    }

    // ---------------------------------------------------------------- Lista administrativa de pacientes

    public function test_la_lista_administrativa_no_avisa_los_mensajes_sin_leer(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $direccion = $this->entrarComoDireccion();
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $ana->profesionales()->attach($direccion->id); // Dirección también la atiende: le llegan mensajes.
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);

        // Tiene dos mensajes sin leer de Ana, pero esta pantalla es de gestión: los avisos son cosa del Inicio.
        $this->assertSame([$ana->id => 2], $direccion->mensajesSinLeer()->all());

        Livewire::test(Lista::class)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('sin leer');
    }

    // ---------------------------------------------------------------- Menú

    public function test_el_menu_muestra_un_puntito_sin_numero_cuando_hay_mensajes_sin_leer(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);

        // Se pide el perfil, que no tiene ningún otro aviso: lo que aparezca solo puede venir del menú.
        $html = $this->actingAs($lucia)
            ->get('/mi-perfil')
            ->assertOk()
            // El puntito va dentro del link "Mis pacientes" y está vacío: no dice cuántos son (acá serían 3).
            ->assertSeeInOrder(['href="'.route('inicio').'"', 'Mis pacientes', self::PUNTITO.'></span>'], false)
            ->getContent();

        // Uno por menú, aunque los mensajes sean de dos pacientes: el del link "Mis pacientes" (desktop)
        // y el del botón ☰ (mobile). En cada tamaño de pantalla se ve uno solo de los dos.
        $this->assertSame(2, substr_count($html, self::PUNTITO));
    }

    public function test_el_menu_no_muestra_el_puntito_cuando_no_hay_mensajes_sin_leer(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee(self::PUNTITO, false);
    }

    public function test_los_mensajes_propios_no_prenden_el_puntito_del_menu(): void
    {
        $ana = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $this->crearMensaje($ana, $lucia);

        $this->actingAs($lucia)->get('/')->assertOk()->assertDontSee(self::PUNTITO, false);
    }

    public function test_los_mensajes_de_un_paciente_inactivo_no_prenden_el_puntito_del_menu(): void
    {
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($bruno, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($bruno, 'Marta Díaz');
        $this->crearMensaje($bruno, $marta);

        // Mientras está activo, el menú avisa (así se sabe que lo que lo apaga es la baja y no otra cosa).
        $this->actingAs($lucia)->get('/mi-perfil')->assertOk()->assertSee(self::PUNTITO, false);

        $bruno->update(['activo' => false]);

        $this->get('/mi-perfil')->assertOk()->assertDontSee(self::PUNTITO, false);
    }

    public function test_al_abrir_la_ficha_el_menu_de_esa_misma_pagina_ya_no_avisa_los_mensajes_de_ese_paciente(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);

        // Antes de entrar, el menú avisa.
        $this->actingAs($lucia)->get('/mi-perfil')->assertSee(self::PUNTITO, false);

        // Lo único sin leer era de Ana. Al abrir su ficha pasan a leídos, y el menú de esa misma respuesta
        // ya no tiene que mostrar el puntito: si lo mostrara, el aviso quedaría viejo hasta cambiar
        // de pantalla (el menú se habría armado antes de marcar como leído).
        $this->get("/pacientes/{$ana->id}")
            ->assertOk()
            ->assertDontSee(self::PUNTITO, false);
    }

    public function test_al_abrir_una_ficha_el_puntito_sigue_si_quedan_mensajes_de_otro_paciente(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($ana, 'Marta Díaz');
        $bruno->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);

        // Abrir la ficha de Ana marca leídos solo los de Ana: el de Bruno sigue pendiente.
        $this->actingAs($lucia)
            ->get("/pacientes/{$ana->id}")
            ->assertOk()
            ->assertSee(self::PUNTITO, false);
    }
}
