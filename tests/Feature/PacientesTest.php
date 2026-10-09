<?php

namespace Tests\Feature;

use App\Livewire\Pacientes\Ficha;
use App\Livewire\Pacientes\Formulario;
use App\Livewire\Pacientes\Lista;
use App\Models\Disciplina;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Pruebas de las pantallas de pacientes: listar, ver la ficha, crear y editar (no hay borrar). */
class PacientesTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /**
     * Crea un paciente directo en la base (no hay factory de pacientes).
     * Por defecto solo lleva los obligatorios; $datos pisa o agrega campos.
     */
    private function crearPaciente(array $datos = []): Paciente
    {
        // refresh() lo vuelve a leer de la base, igual que cuando la app lo recibe por la URL.
        // Sin eso, "activo" quedaría en null en memoria (su valor por defecto lo pone la base, no el modelo).
        return Paciente::create(array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
        ], $datos))->refresh();
    }

    /** Abre el formulario de alta con los cuatro obligatorios ya cargados; $campos pisa los que se quieran probar. */
    private function formularioCompleto(array $campos = [])
    {
        $formulario = Livewire::test(Formulario::class);

        $valores = array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
        ], $campos);

        foreach ($valores as $campo => $valor) {
            $formulario->set($campo, $valor);
        }

        return $formulario;
    }

    // ---------------------------------------------------------------- Rutas

    public function test_sin_sesion_las_pantallas_de_pacientes_redirigen_al_login(): void
    {
        $paciente = $this->crearPaciente();

        $this->get('/pacientes')->assertRedirect('/login');
        $this->get('/pacientes/crear')->assertRedirect('/login');
        $this->get("/pacientes/{$paciente->id}")->assertRedirect('/login');
        $this->get("/pacientes/{$paciente->id}/editar")->assertRedirect('/login');
    }

    public function test_con_sesion_la_lista_de_pacientes_se_muestra(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pacientes')
            ->assertOk()
            ->assertSeeLivewire(Lista::class);
    }

    public function test_con_sesion_la_pantalla_de_crear_paciente_se_muestra(): void
    {
        // "crear" no se tiene que confundir con el id de un paciente.
        $this->actingAs(User::factory()->create())
            ->get('/pacientes/crear')
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            ->assertSee('Nuevo paciente');
    }

    public function test_con_sesion_la_ficha_del_paciente_se_muestra(): void
    {
        $paciente = $this->crearPaciente();

        $this->actingAs(User::factory()->create())
            ->get("/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSeeLivewire(Ficha::class);
    }

    public function test_con_sesion_la_pantalla_de_editar_paciente_se_muestra(): void
    {
        $paciente = $this->crearPaciente();

        $this->actingAs(User::factory()->create())
            ->get("/pacientes/{$paciente->id}/editar")
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            ->assertSee('Editar paciente');
    }

    public function test_la_ficha_de_un_paciente_que_no_existe_da_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/pacientes/9999')->assertNotFound();
    }

    public function test_editar_un_paciente_que_no_existe_da_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/pacientes/9999/editar')->assertNotFound();
    }

    // ---------------------------------------------------------------- Lista

    public function test_la_lista_muestra_los_pacientes_ordenados_por_apellido_con_dni_y_estado(): void
    {
        // Se cargan desordenados a propósito: la lista los tiene que ordenar por apellido.
        $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304', 'activo' => false]);
        $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222', 'activo' => true]);

        Livewire::test(Lista::class)
            ->assertSeeInOrder([
                'Arnal, Bruno', '40.111.222', 'Activo',
                'Zapata, Ana', '52.118.304', 'Inactivo',
            ])
            ->assertDontSee('Todavía no hay pacientes cargados.');
    }

    public function test_la_lista_muestra_los_nombres_del_equipo_de_cada_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $marta = User::factory()->create(['name' => 'Marta Díaz']);
        User::factory()->create(['name' => 'Pedro Suelto']); // No atiende a nadie: no tiene que aparecer.
        $paciente->profesionales()->sync([$lucia->id, $marta->id]);

        Livewire::test(Lista::class)
            ->assertSee('Lucía Pérez')
            ->assertSee('Marta Díaz')
            ->assertDontSee('Pedro Suelto');
    }

    public function test_sin_pacientes_la_lista_avisa_que_esta_vacia(): void
    {
        Livewire::test(Lista::class)->assertSee('Todavía no hay pacientes cargados.');
    }

    // ---------------------------------------------------------------- Ficha

    public function test_la_ficha_muestra_los_datos_del_paciente(): void
    {
        $this->travelTo('2026-10-08 12:00:00'); // Fecha fija, para que la edad no dependa del día.

        $paciente = $this->crearPaciente([
            'fecha_nacimiento' => '2018-03-15',
            'fecha_ingreso' => '2024-04-02',
            'obra_social' => 'OSDE',
            'escuela' => 'Escuela 12',
            'grado' => 'Tercero',
            'diagnostico' => 'Retraso del lenguaje',
        ]);

        $html = Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->assertSee('Ana Zapata')
            ->assertSee('Activo')
            ->assertSee('52.118.304')
            ->assertSee('15/03/2018')
            ->assertSee('(8 años)')
            ->assertSee('02/04/2024')
            ->assertSee('OSDE')
            ->assertSee('Escuela 12')
            ->assertSee('Tercero')
            ->assertSee('Retraso del lenguaje')
            ->html();

        // Con todos los opcionales cargados no queda ninguna raya.
        $this->assertSame(0, substr_count($html, '—'));
    }

    public function test_la_ficha_muestra_una_raya_en_los_opcionales_vacios(): void
    {
        $paciente = $this->crearPaciente(); // Solo los obligatorios.

        $html = Livewire::test(Ficha::class, ['paciente' => $paciente])->html();

        // Una raya por cada opcional: ingreso, obra social, escuela, grado y diagnóstico.
        $this->assertSame(5, substr_count($html, '—'));
    }

    public function test_la_ficha_de_un_paciente_dado_de_baja_dice_inactivo(): void
    {
        $paciente = $this->crearPaciente(['activo' => false]);

        Livewire::test(Ficha::class, ['paciente' => $paciente])->assertSee('Inactivo');
    }

    public function test_la_ficha_muestra_el_equipo_con_la_disciplina_de_cada_profesional(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create(['name' => 'Lucía Pérez', 'disciplina_id' => $psicologia->id]);
        $marta = User::factory()->create(['name' => 'Marta Díaz']); // Sin disciplina.
        $paciente->profesionales()->sync([$lucia->id, $marta->id]);

        Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Lucía Pérez', 'Psicología'])
            ->assertSee('Marta Díaz')
            ->assertDontSee('Todavía no tiene profesionales asignados.');
    }

    public function test_la_ficha_avisa_cuando_el_paciente_no_tiene_equipo(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->assertSee('Todavía no tiene profesionales asignados.');
    }

    // ---------------------------------------------------------------- Crear

    public function test_se_puede_crear_un_paciente_solo_con_los_obligatorios(): void
    {
        $formulario = $this->formularioCompleto()->call('guardar')->assertHasNoErrors();

        $paciente = Paciente::firstOrFail();

        $formulario->assertRedirect(route('pacientes.ver', $paciente));

        $this->assertDatabaseCount('pacientes', 1);
        $this->assertSame('Ana', $paciente->nombre);
        $this->assertSame('Zapata', $paciente->apellido);
        $this->assertSame('52118304', $paciente->dni);
        $this->assertSame('2018-10-08', $paciente->fecha_nacimiento->format('Y-m-d'));

        // Los opcionales vacíos quedan en null, no como texto vacío.
        $this->assertNull($paciente->fecha_ingreso);
        $this->assertNull($paciente->obra_social);
        $this->assertNull($paciente->escuela);
        $this->assertNull($paciente->grado);
        $this->assertNull($paciente->diagnostico);

        // Un paciente nuevo siempre nace activo y sin equipo.
        $this->assertTrue($paciente->activo);
        $this->assertDatabaseCount('paciente_user', 0);
    }

    public function test_se_puede_crear_un_paciente_con_todos_los_campos(): void
    {
        $this->formularioCompleto([
            'fecha_ingreso' => '2024-04-02',
            'obra_social' => 'OSDE',
            'escuela' => 'Escuela 12',
            'grado' => 'Tercero',
            'diagnostico' => 'Retraso del lenguaje',
        ])->call('guardar')->assertHasNoErrors();

        $paciente = Paciente::firstOrFail();

        $this->assertSame('2024-04-02', $paciente->fecha_ingreso->format('Y-m-d'));
        $this->assertSame('OSDE', $paciente->obra_social);
        $this->assertSame('Escuela 12', $paciente->escuela);
        $this->assertSame('Tercero', $paciente->grado);
        $this->assertSame('Retraso del lenguaje', $paciente->diagnostico);
    }

    public function test_se_puede_crear_un_paciente_con_equipo(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        $pedro = User::factory()->create(); // No se tilda: no tiene que quedar vinculado.

        // Las casillas mandan los ids como texto, por eso se pasan como string.
        $this->formularioCompleto(['equipo' => [(string) $lucia->id, (string) $marta->id]])
            ->call('guardar')
            ->assertHasNoErrors();

        $paciente = Paciente::firstOrFail();

        $this->assertDatabaseCount('paciente_user', 2);
        $this->assertDatabaseHas('paciente_user', ['paciente_id' => $paciente->id, 'user_id' => $lucia->id]);
        $this->assertDatabaseHas('paciente_user', ['paciente_id' => $paciente->id, 'user_id' => $marta->id]);
        $this->assertDatabaseMissing('paciente_user', ['user_id' => $pedro->id]);
    }

    public function test_al_crear_no_se_muestra_la_casilla_de_activo(): void
    {
        Livewire::test(Formulario::class)->assertDontSee('destildar para darlo de baja');
    }

    public function test_el_dni_escrito_con_puntos_se_guarda_sin_puntos(): void
    {
        $this->formularioCompleto(['dni' => '52.118.304'])->call('guardar')->assertHasNoErrors();

        $this->assertSame('52118304', Paciente::firstOrFail()->dni);
    }

    public function test_el_dni_escrito_con_espacios_se_guarda_solo_con_numeros(): void
    {
        $this->formularioCompleto(['dni' => ' 52 118 304 '])->call('guardar')->assertHasNoErrors();

        $this->assertSame('52118304', Paciente::firstOrFail()->dni);
    }

    // ---------------------------------------------------------------- Validación

    public function test_el_nombre_vacio_da_error(): void
    {
        $this->formularioCompleto(['nombre' => ''])
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);

        $this->assertDatabaseCount('pacientes', 0);
    }

    public function test_el_apellido_vacio_da_error(): void
    {
        $this->formularioCompleto(['apellido' => ''])
            ->call('guardar')
            ->assertHasErrors(['apellido' => 'required']);

        $this->assertDatabaseCount('pacientes', 0);
    }

    public function test_el_dni_vacio_da_error(): void
    {
        $this->formularioCompleto(['dni' => ''])
            ->call('guardar')
            ->assertHasErrors(['dni' => 'required']);

        $this->assertDatabaseCount('pacientes', 0);
    }

    public function test_la_fecha_de_nacimiento_vacia_da_error(): void
    {
        $this->formularioCompleto(['fecha_nacimiento' => ''])
            ->call('guardar')
            ->assertHasErrors(['fecha_nacimiento' => 'required']);

        $this->assertDatabaseCount('pacientes', 0);
    }

    public function test_un_dni_duplicado_da_error(): void
    {
        $this->crearPaciente(['dni' => '52118304']);

        $this->formularioCompleto(['nombre' => 'Otra', 'dni' => '52118304'])
            ->call('guardar')
            ->assertHasErrors(['dni' => 'unique']);

        $this->assertDatabaseCount('pacientes', 1);
    }

    public function test_un_dni_duplicado_escrito_con_puntos_tambien_da_error(): void
    {
        $this->crearPaciente(['dni' => '52118304']);

        // Sin quitarle los puntos, "52.118.304" pasaría como un DNI distinto.
        $this->formularioCompleto(['nombre' => 'Otra', 'dni' => '52.118.304'])
            ->call('guardar')
            ->assertHasErrors(['dni' => 'unique']);

        $this->assertDatabaseCount('pacientes', 1);
    }

    public function test_una_fecha_de_nacimiento_futura_da_error(): void
    {
        $this->formularioCompleto(['fecha_nacimiento' => now()->addDay()->format('Y-m-d')])
            ->call('guardar')
            ->assertHasErrors(['fecha_nacimiento' => 'before_or_equal']);

        $this->assertDatabaseCount('pacientes', 0);
    }

    public function test_un_profesional_inexistente_en_el_equipo_da_error(): void
    {
        $this->formularioCompleto(['equipo' => ['999']]) // No hay ningún usuario con ese id.
            ->call('guardar')
            ->assertHasErrors(['equipo.0' => 'exists']);

        $this->assertDatabaseCount('pacientes', 0);
        $this->assertDatabaseCount('paciente_user', 0);
    }

    // ---------------------------------------------------------------- Editar

    public function test_editar_carga_los_datos_del_paciente_con_su_equipo(): void
    {
        $paciente = $this->crearPaciente([
            'fecha_ingreso' => '2024-04-02',
            'obra_social' => 'OSDE',
            'escuela' => 'Escuela 12',
            'grado' => 'Tercero',
            'diagnostico' => 'Retraso del lenguaje',
            'activo' => false,
        ]);
        $lucia = User::factory()->create();
        User::factory()->create(); // Existe pero no es del equipo.
        $paciente->profesionales()->sync([$lucia->id]);

        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->assertSet('pacienteId', $paciente->id)
            ->assertSet('nombre', 'Ana')
            ->assertSet('apellido', 'Zapata')
            ->assertSet('dni', '52118304')
            ->assertSet('fecha_nacimiento', '2018-10-08')
            ->assertSet('fecha_ingreso', '2024-04-02')
            ->assertSet('obra_social', 'OSDE')
            ->assertSet('escuela', 'Escuela 12')
            ->assertSet('grado', 'Tercero')
            ->assertSet('diagnostico', 'Retraso del lenguaje')
            ->assertSet('activo', false)
            // Los ids del equipo van como texto, para que coincidan con las casillas.
            ->assertSet('equipo', [(string) $lucia->id])
            // Al editar sí aparece la casilla para dar de baja.
            ->assertSee('destildar para darlo de baja');
    }

    public function test_editar_un_paciente_sin_opcionales_deja_esos_campos_vacios(): void
    {
        $paciente = $this->crearPaciente();

        // null se convierte en '' para que los campos del formulario queden en blanco.
        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->assertSet('fecha_ingreso', '')
            ->assertSet('obra_social', '')
            ->assertSet('escuela', '')
            ->assertSet('grado', '')
            ->assertSet('diagnostico', '')
            ->assertSet('activo', true)
            ->assertSet('equipo', []);
    }

    public function test_guardar_una_edicion_actualiza_los_datos_sin_crear_otro_paciente(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('nombre', 'Ana María')
            ->set('apellido', 'Zapata Ruiz')
            ->set('dni', '40.111.222')
            ->set('fecha_nacimiento', '2017-01-20')
            ->set('obra_social', 'OSDE')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('pacientes.ver', $paciente));

        $paciente->refresh();

        // Se actualizó el existente, no se creó otro.
        $this->assertDatabaseCount('pacientes', 1);
        $this->assertSame('Ana María', $paciente->nombre);
        $this->assertSame('Zapata Ruiz', $paciente->apellido);
        $this->assertSame('40111222', $paciente->dni);
        $this->assertSame('2017-01-20', $paciente->fecha_nacimiento->format('Y-m-d'));
        $this->assertSame('OSDE', $paciente->obra_social);
    }

    public function test_se_puede_guardar_una_edicion_sin_cambiar_el_dni(): void
    {
        $paciente = $this->crearPaciente();

        // La regla de único no cuenta al propio paciente que se está editando.
        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('nombre', 'Ana María')
            ->call('guardar')
            ->assertHasNoErrors();

        $paciente->refresh();

        $this->assertDatabaseCount('pacientes', 1);
        $this->assertSame('Ana María', $paciente->nombre);
        $this->assertSame('52118304', $paciente->dni);
    }

    public function test_al_editar_no_se_puede_usar_el_dni_de_otro_paciente(): void
    {
        $this->crearPaciente(['dni' => '52118304']);
        $otro = $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222']);

        // La regla de único solo ignora al que se edita, no a los demás.
        Livewire::test(Formulario::class, ['paciente' => $otro])
            ->set('dni', '52.118.304')
            ->call('guardar')
            ->assertHasErrors(['dni' => 'unique']);

        $this->assertSame('40111222', $otro->fresh()->dni);
    }

    public function test_cambiar_el_equipo_al_editar_agrega_y_saca_profesionales(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        $pedro = User::factory()->create();
        $paciente->profesionales()->sync([$lucia->id, $marta->id]);

        // Se destilda a Lucía, queda Marta y se tilda a Pedro.
        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('equipo', [(string) $marta->id, (string) $pedro->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('paciente_user', 2);
        $this->assertDatabaseMissing('paciente_user', ['paciente_id' => $paciente->id, 'user_id' => $lucia->id]);
        $this->assertDatabaseHas('paciente_user', ['paciente_id' => $paciente->id, 'user_id' => $marta->id]);
        $this->assertDatabaseHas('paciente_user', ['paciente_id' => $paciente->id, 'user_id' => $pedro->id]);
    }

    public function test_destildar_activo_al_editar_da_de_baja_al_paciente(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('activo', false)
            ->call('guardar')
            ->assertHasNoErrors();

        // Se da de baja sin borrarlo: sigue en la base, pero inactivo.
        $this->assertDatabaseCount('pacientes', 1);
        $this->assertFalse($paciente->fresh()->activo);
    }

    public function test_vaciar_un_opcional_al_editar_lo_deja_en_null(): void
    {
        $paciente = $this->crearPaciente(['obra_social' => 'OSDE', 'fecha_ingreso' => '2024-04-02']);

        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('obra_social', '')
            ->set('fecha_ingreso', '')
            ->call('guardar')
            ->assertHasNoErrors();

        $paciente->refresh();

        $this->assertNull($paciente->obra_social);
        $this->assertNull($paciente->fecha_ingreso);
    }

    // ---------------------------------------------------------------- Modelo

    public function test_el_accesor_nombre_completo_devuelve_apellido_coma_nombre(): void
    {
        $this->assertSame('Zapata, Ana', $this->crearPaciente()->nombre_completo);
    }

    public function test_el_accesor_edad_devuelve_los_anios_cumplidos(): void
    {
        $this->travelTo('2026-10-08 12:00:00'); // Fecha fija, para que no dependa del día en que se corre.

        // Cumple hoy: ya tiene 8.
        $this->assertSame(8, $this->crearPaciente(['dni' => '1', 'fecha_nacimiento' => '2018-10-08'])->edad);
        // Cumple mañana: todavía tiene 7.
        $this->assertSame(7, $this->crearPaciente(['dni' => '2', 'fecha_nacimiento' => '2018-10-09'])->edad);
        // Nació hace menos de un año: 0.
        $this->assertSame(0, $this->crearPaciente(['dni' => '3', 'fecha_nacimiento' => '2026-03-01'])->edad);
    }

    public function test_el_accesor_dni_con_puntos_separa_los_miles(): void
    {
        $this->assertSame('52.118.304', $this->crearPaciente(['dni' => '52118304'])->dni_con_puntos);
        // Un DNI de siete dígitos también queda bien separado.
        $this->assertSame('5.123.456', $this->crearPaciente(['dni' => '5123456'])->dni_con_puntos);
    }
}
