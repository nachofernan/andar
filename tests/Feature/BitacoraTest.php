<?php

namespace Tests\Feature;

use App\Livewire\Pacientes\Ficha;
use App\Livewire\Pacientes\Formulario;
use App\Livewire\Pacientes\Mensajes;
use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de la bitácora del paciente: cada vez que alguien cambia sus datos, la app guarda sola en su historial
 * una entrada de tipo "actualizacion" con lo que había antes y lo que quedó (Paciente::actualizar).
 *
 * Cubre el modelo (qué se anota y con qué formato), los dos caminos que lo usan (el formulario de administración
 * al editar y el diagnóstico de la ficha, que lo cambia el equipo) y cómo se ve la entrada en el historial.
 *
 * Después de una acción (call o set) se usa assertSeeHtml y no assertSee: este último mira la respuesta cruda
 * de Livewire, que tras una acción viene como JSON con las tildes escritas en código.
 */
class BitacoraTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /**
     * Crea un paciente directo en la base (no hay factory de pacientes), sin dejar entrada en la bitácora.
     * Por defecto solo lleva los obligatorios; $datos pisa o agrega campos.
     */
    private function crearPaciente(array $datos = []): Paciente
    {
        // refresh() lo vuelve a leer de la base, igual que cuando la app lo recibe por la URL.
        return Paciente::create(array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
        ], $datos))->refresh();
    }

    /** Crea un profesional y lo suma al equipo del paciente. */
    private function crearProfesionalDelEquipo(Paciente $paciente, string $nombre = 'Lucía Pérez'): User
    {
        $profesional = User::factory()->create(['name' => $nombre]);
        $paciente->profesionales()->attach($profesional->id);

        return $profesional;
    }

    /** Las entradas de bitácora que hay en la base, de la más vieja a la más nueva. */
    private function entradas(): Collection
    {
        return Mensaje::where('tipo', 'actualizacion')->orderBy('id')->get();
    }

    /** Comprueba que haya UNA sola entrada (y ningún otro mensaje) y devuelve sus cambios ya decodificados. */
    private function cambiosDeLaUnicaEntrada(): array
    {
        $this->assertDatabaseCount('mensajes', 1);

        return $this->entradas()->sole()->cambios;
    }

    /** Ids del equipo del paciente, ordenados, leídos de la base. */
    private function equipoDe(Paciente $paciente): array
    {
        return $paciente->profesionales()->orderBy('users.id')->pluck('users.id')->all();
    }

    // ---------------------------------------------------------------- Modelo: qué se anota

    public function test_cambiar_un_dato_guarda_el_cambio_y_deja_una_entrada_con_lo_de_antes_y_lo_de_ahora(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);

        // El dato se guardó.
        $this->assertSame('Tercero', $paciente->fresh()->grado);

        // Y quedó una entrada en el historial de ESE paciente, a nombre de quien hizo el cambio.
        $entrada = $this->entradas()->sole();

        $this->assertSame($paciente->id, $entrada->paciente_id);
        $this->assertSame($lucia->id, $entrada->user_id);
        $this->assertSame('actualizacion', $entrada->tipo);
        $this->assertTrue($entrada->esActualizacion());
        $this->assertSame([['campo' => 'Grado', 'antes' => 'Segundo', 'ahora' => 'Tercero']], $entrada->cambios);
    }

    public function test_los_datos_que_se_registran_son_los_diez_del_paciente_con_su_nombre_para_mostrar(): void
    {
        $this->assertSame([
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'dni' => 'DNI',
            'fecha_nacimiento' => 'Fecha de nacimiento',
            'fecha_ingreso' => 'Ingreso al espacio',
            'obra_social' => 'Obra social',
            'escuela' => 'Escuela',
            'grado' => 'Grado',
            'diagnostico' => 'Diagnóstico',
            'activo' => 'Estado',
        ], Paciente::DATOS_REGISTRADOS);
    }

    public function test_varios_cambios_en_un_guardado_dejan_una_sola_entrada_con_cada_valor_escrito_como_se_lee(): void
    {
        $paciente = $this->crearPaciente(); // Solo los obligatorios: los opcionales arrancan vacíos.
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $paciente->actualizar([
            'nombre' => 'Anahí',
            'apellido' => 'Zabala',
            'dni' => '40111222',
            'fecha_nacimiento' => '2018-03-15',
            'fecha_ingreso' => '2024-04-02',
            'obra_social' => 'OSDE',
            'escuela' => 'Escuela 12',
            'grado' => 'Tercero',
            'diagnostico' => 'Retraso del lenguaje',
            'activo' => false,
        ], null, $lucia);

        // Una sola entrada con los diez cambios, en el orden de DATOS_REGISTRADOS.
        // Fechas día/mes/año, DNI con puntos, estado con palabras y lo que estaba vacío como null.
        $this->assertSame([
            ['campo' => 'Nombre', 'antes' => 'Ana', 'ahora' => 'Anahí'],
            ['campo' => 'Apellido', 'antes' => 'Zapata', 'ahora' => 'Zabala'],
            ['campo' => 'DNI', 'antes' => '52.118.304', 'ahora' => '40.111.222'],
            ['campo' => 'Fecha de nacimiento', 'antes' => '08/10/2018', 'ahora' => '15/03/2018'],
            ['campo' => 'Ingreso al espacio', 'antes' => null, 'ahora' => '02/04/2024'],
            ['campo' => 'Obra social', 'antes' => null, 'ahora' => 'OSDE'],
            ['campo' => 'Escuela', 'antes' => null, 'ahora' => 'Escuela 12'],
            ['campo' => 'Grado', 'antes' => null, 'ahora' => 'Tercero'],
            ['campo' => 'Diagnóstico', 'antes' => null, 'ahora' => 'Retraso del lenguaje'],
            ['campo' => 'Estado', 'antes' => 'Activo', 'ahora' => 'Inactivo'],
        ], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_solo_se_anotan_los_datos_que_cambiaron_aunque_se_manden_todos(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo', 'obra_social' => 'OSDE']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        // Así llega desde el formulario: todos los campos, haya cambiado uno solo.
        $paciente->actualizar([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
            'fecha_ingreso' => null,
            'obra_social' => 'OSDE',
            'escuela' => null,
            'grado' => 'Tercero',
            'diagnostico' => null,
            'activo' => true,
        ], null, $lucia);

        $this->assertSame([['campo' => 'Grado', 'antes' => 'Segundo', 'ahora' => 'Tercero']], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_vaciar_un_dato_anota_null_como_valor_nuevo(): void
    {
        $paciente = $this->crearPaciente(['obra_social' => 'OSDE', 'fecha_ingreso' => '2024-04-02']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $paciente->actualizar(['obra_social' => null, 'fecha_ingreso' => null], null, $lucia);

        $this->assertSame([
            ['campo' => 'Ingreso al espacio', 'antes' => '02/04/2024', 'ahora' => null],
            ['campo' => 'Obra social', 'antes' => 'OSDE', 'ahora' => null],
        ], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_volver_a_dar_de_alta_anota_el_estado_de_inactivo_a_activo(): void
    {
        $paciente = $this->crearPaciente(['activo' => false]);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $paciente->actualizar(['activo' => true], null, $lucia);

        $this->assertSame([['campo' => 'Estado', 'antes' => 'Inactivo', 'ahora' => 'Activo']], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_si_no_cambio_nada_no_se_crea_ninguna_entrada(): void
    {
        $paciente = $this->crearPaciente(['fecha_ingreso' => '2024-04-02', 'grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        // Los mismos valores que ya tenía (las fechas llegan como texto año-mes-día, igual que del formulario),
        // y el mismo equipo.
        $paciente->actualizar([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
            'fecha_ingreso' => '2024-04-02',
            'obra_social' => null,
            'escuela' => null,
            'grado' => 'Segundo',
            'diagnostico' => null,
            'activo' => true,
        ], [(string) $lucia->id], $lucia);

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_cada_guardado_con_cambios_deja_su_propia_entrada(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Primero']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        $paciente->actualizar(['grado' => 'Segundo'], null, $lucia);
        $paciente->actualizar(['grado' => 'Segundo'], null, $lucia); // Sin cambios: no suma.
        $paciente->actualizar(['grado' => 'Tercero'], null, $marta);

        $entradas = $this->entradas();

        // Dos entradas, cada una con su autor y con el "antes" que había en ese momento.
        $this->assertCount(2, $entradas);
        $this->assertSame([$lucia->id, $marta->id], $entradas->pluck('user_id')->all());
        $this->assertSame([['campo' => 'Grado', 'antes' => 'Primero', 'ahora' => 'Segundo']], $entradas[0]->cambios);
        $this->assertSame([['campo' => 'Grado', 'antes' => 'Segundo', 'ahora' => 'Tercero']], $entradas[1]->cambios);
    }

    // ---------------------------------------------------------------- Modelo: equipo

    public function test_sumar_profesionales_al_equipo_lo_anota_con_sus_nombres_por_orden_alfabetico(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        // Se crean desordenados: en la entrada tienen que salir por nombre.
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz']);
        $ana = User::factory()->create(['name' => 'Ana Gómez']);

        $paciente->actualizar([], [$lucia->id, $zulema->id, $ana->id], $lucia);

        // Los cambios de equipo no llevan antes/ahora: llevan el texto ya escrito.
        $this->assertSame([['campo' => 'Equipo', 'texto' => 'Se sumó: Ana Gómez, Zulema Ruiz']], $this->cambiosDeLaUnicaEntrada());
        $this->assertSame([$lucia->id, $zulema->id, $ana->id], $this->equipoDe($paciente));
    }

    public function test_sacar_profesionales_del_equipo_lo_anota(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        $paciente->actualizar([], [$lucia->id], $lucia);

        $this->assertSame([['campo' => 'Equipo', 'texto' => 'Salió: Marta Díaz']], $this->cambiosDeLaUnicaEntrada());
        $this->assertSame([$lucia->id], $this->equipoDe($paciente));
    }

    public function test_sumar_y_sacar_en_el_mismo_guardado_va_en_una_sola_entrada_junto_con_los_datos(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $pedro = User::factory()->create(['name' => 'Pedro Ruiz']);

        // Los ids como texto, igual que los mandan las casillas del formulario.
        $paciente->actualizar(['grado' => 'Tercero'], [(string) $lucia->id, (string) $pedro->id], $lucia);

        // Primero los datos, después quién se sumó y quién salió.
        $this->assertSame([
            ['campo' => 'Grado', 'antes' => 'Segundo', 'ahora' => 'Tercero'],
            ['campo' => 'Equipo', 'texto' => 'Se sumó: Pedro Ruiz'],
            ['campo' => 'Equipo', 'texto' => 'Salió: Marta Díaz'],
        ], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_dejar_al_paciente_sin_equipo_anota_la_salida_de_todos(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        // Lista vacía = equipo vacío (no es lo mismo que null).
        $paciente->actualizar([], [], $lucia);

        $this->assertSame([['campo' => 'Equipo', 'texto' => 'Salió: Lucía Pérez, Marta Díaz']], $this->cambiosDeLaUnicaEntrada());
        $this->assertSame([], $this->equipoDe($paciente));
    }

    public function test_el_equipo_en_null_no_toca_el_equipo_ni_anota_nada_sobre_el(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        // Así lo usa la ficha al cambiar solo el diagnóstico.
        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);

        // El equipo quedó como estaba y la entrada solo habla del dato.
        $this->assertSame([$lucia->id, $marta->id], $this->equipoDe($paciente));
        $this->assertSame([['campo' => 'Grado', 'antes' => 'Segundo', 'ahora' => 'Tercero']], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_el_equipo_en_null_y_sin_cambios_en_los_datos_no_crea_ninguna_entrada(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $paciente->actualizar(['grado' => 'Segundo'], null, $lucia);

        $this->assertDatabaseCount('mensajes', 0);
        $this->assertSame([$lucia->id], $this->equipoDe($paciente));
    }

    // ---------------------------------------------------------------- Modelo: cifrado, autor, sin leer, transacción

    public function test_la_entrada_se_guarda_cifrada_como_cualquier_mensaje(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $pedro = User::factory()->create(['name' => 'Pedro Ruiz']);

        $paciente->actualizar(['diagnostico' => 'Trastorno específico del lenguaje'], [$lucia->id, $pedro->id], $lucia);

        // La fila cruda, sin pasar por el modelo: es lo que vería alguien que abre el archivo de la base.
        $fila = DB::table('mensajes')->sole();

        // No se lee ni lo anterior, ni lo nuevo, ni el nombre del campo, ni quién se sumó al equipo.
        foreach (['Retraso del lenguaje', 'Trastorno', 'Diagnóstico', 'Diagn', 'Pedro Ruiz', 'campo', 'antes'] as $texto) {
            $this->assertStringNotContainsString($texto, $fila->cuerpo);
        }

        // Leído por el modelo vuelve la lista de cambios, con las tildes tal cual (no escritas en código).
        $entrada = Mensaje::sole();

        $this->assertStringContainsString('"campo":"Diagnóstico"', $entrada->cuerpo);
        $this->assertSame([
            ['campo' => 'Diagnóstico', 'antes' => 'Retraso del lenguaje', 'ahora' => 'Trastorno específico del lenguaje'],
            ['campo' => 'Equipo', 'texto' => 'Se sumó: Pedro Ruiz'],
        ], $entrada->cambios);
    }

    public function test_un_mensaje_comun_no_tiene_cambios_aunque_su_texto_parezca_una_lista_de_cambios(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $mensaje = Mensaje::create([
            'paciente_id' => $paciente->id,
            'user_id' => $lucia->id,
            'tipo' => 'mensaje',
            'cuerpo' => '[{"campo":"Grado","antes":"Segundo","ahora":"Tercero"}]',
        ]);

        // Solo las entradas de tipo "actualizacion" se leen como cambios.
        $this->assertFalse($mensaje->esActualizacion());
        $this->assertSame([], $mensaje->cambios);
    }

    public function test_la_entrada_cuenta_como_sin_leer_para_el_resto_del_equipo_y_no_para_quien_hizo_el_cambio(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $pedro = User::factory()->create(); // No es del equipo: no le llega nada.

        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);

        $this->assertSame([], $lucia->mensajesSinLeer()->all());
        $this->assertSame([$paciente->id => 1], $marta->mensajesSinLeer()->all());
        $this->assertSame([], $pedro->mensajesSinLeer()->all());
    }

    public function test_al_abrir_el_historial_la_entrada_pasa_a_leida(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        $this->travelTo('2026-10-08 10:00:00');
        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);

        $this->travelTo('2026-10-08 10:05:00');
        Livewire::actingAs($marta)->test(Mensajes::class, ['paciente' => $paciente]);

        $this->assertSame([], $marta->mensajesSinLeer()->all());
    }

    public function test_si_el_guardado_falla_a_mitad_de_camino_no_queda_nada_a_medias(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        // Un id de usuario que no existe: los datos se llegan a guardar, pero el equipo falla.
        try {
            $paciente->actualizar(['grado' => 'Tercero'], [$lucia->id, 99999], $lucia);
            $this->fail('Tendría que haber fallado al sumar al equipo un usuario que no existe.');
        } catch (QueryException) {
            // Era lo esperado.
        }

        // Todo va en una transacción: ni el dato, ni el equipo, ni la entrada.
        $this->assertSame('Segundo', $paciente->fresh()->grado);
        $this->assertSame([$lucia->id], $this->equipoDe($paciente));
        $this->assertDatabaseCount('mensajes', 0);
    }

    // ---------------------------------------------------------------- Formulario de administración

    public function test_crear_un_paciente_no_deja_ninguna_entrada(): void
    {
        $direccion = $this->entrarComoDireccion();
        $lucia = User::factory()->create();

        // Con todos los datos y con equipo: aun así, cargar un paciente nuevo no es un "cambio".
        Livewire::test(Formulario::class)
            ->set('nombre', 'Ana')
            ->set('apellido', 'Zapata')
            ->set('dni', '52118304')
            ->set('fecha_nacimiento', '2018-10-08')
            ->set('fecha_ingreso', '2024-04-02')
            ->set('obra_social', 'OSDE')
            ->set('diagnostico', 'Retraso del lenguaje')
            ->set('equipo', [(string) $lucia->id, (string) $direccion->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('pacientes', 1);
        $this->assertDatabaseCount('paciente_user', 2);
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_editar_desde_el_formulario_deja_una_sola_entrada_con_todos_los_cambios_a_nombre_de_quien_edito(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo', 'obra_social' => 'OSDE']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $pedro = User::factory()->create(['name' => 'Pedro Ruiz']);
        $direccion = $this->entrarComoDireccion(['name' => 'Catalina Arnal']); // No es del equipo: igual queda como autora.

        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('grado', 'Tercero')
            ->set('obra_social', '')
            ->set('fecha_ingreso', '2024-04-02')
            ->set('activo', false)
            ->set('equipo', [(string) $lucia->id, (string) $pedro->id])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('pacientes.ver', $paciente));

        $this->assertSame([
            ['campo' => 'Ingreso al espacio', 'antes' => null, 'ahora' => '02/04/2024'],
            ['campo' => 'Obra social', 'antes' => 'OSDE', 'ahora' => null],
            ['campo' => 'Grado', 'antes' => 'Segundo', 'ahora' => 'Tercero'],
            ['campo' => 'Estado', 'antes' => 'Activo', 'ahora' => 'Inactivo'],
            ['campo' => 'Equipo', 'texto' => 'Se sumó: Pedro Ruiz'],
            ['campo' => 'Equipo', 'texto' => 'Salió: Marta Díaz'],
        ], $this->cambiosDeLaUnicaEntrada());

        $this->assertSame($direccion->id, $this->entradas()->sole()->user_id);
        $this->assertSame($paciente->id, $this->entradas()->sole()->paciente_id);
    }

    public function test_guardar_el_formulario_de_edicion_sin_tocar_nada_no_deja_entrada(): void
    {
        // Con todos los campos cargados: lo que el formulario lee y vuelve a mandar tiene que ser igual a lo guardado.
        $paciente = $this->crearPaciente([
            'fecha_ingreso' => '2024-04-02',
            'obra_social' => 'OSDE',
            'escuela' => 'Escuela 12',
            'grado' => 'Tercero',
            'diagnostico' => "Retraso del lenguaje.\nEn seguimiento.",
        ]);
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->entrarComoDireccion();

        Livewire::test(Formulario::class, ['paciente' => $paciente])->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseCount('mensajes', 0);
        $this->assertSame([$lucia->id], $this->equipoDe($paciente));
    }

    public function test_guardar_el_formulario_de_edicion_de_un_paciente_sin_opcionales_ni_equipo_tampoco_deja_entrada(): void
    {
        $paciente = $this->crearPaciente(['activo' => false]);
        $this->entrarComoDireccion();

        Livewire::test(Formulario::class, ['paciente' => $paciente])->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_escribir_lo_mismo_de_otra_forma_no_cuenta_como_cambio(): void
    {
        $paciente = $this->crearPaciente(['obra_social' => 'OSDE']);
        $this->entrarComoDireccion();

        // El DNI con puntos y los textos con espacios de más se guardan igual que antes: no hay nada que anotar.
        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('dni', '52.118.304')
            ->set('nombre', '  Ana ')
            ->set('obra_social', ' OSDE  ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_una_edicion_con_errores_de_validacion_no_deja_entrada(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $this->entrarComoDireccion();

        Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('grado', 'Tercero')
            ->set('nombre', '') // Obligatorio: no se guarda nada.
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);

        $this->assertSame('Segundo', $paciente->fresh()->grado);
        $this->assertDatabaseCount('mensajes', 0);
    }

    // ---------------------------------------------------------------- Ficha: el equipo edita el diagnóstico

    public function test_el_equipo_ve_el_boton_para_editar_el_diagnostico_y_al_usarlo_se_abre_con_el_texto_actual(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);
        $lucia = $this->crearProfesionalDelEquipo($paciente); // Profesional común: no hace falta ser de Dirección.

        Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertViewHas('esDelEquipo', true)
            ->assertSet('editandoDiagnostico', false)
            ->assertSeeHtml('wire:click="editarDiagnostico"')
            ->assertDontSeeHtml('wire:model="diagnostico"') // Todavía no hay cuadro de texto.
            ->call('editarDiagnostico')
            ->assertSet('editandoDiagnostico', true)
            ->assertSet('diagnostico', 'Retraso del lenguaje')
            // Aparece el cuadro de texto con sus botones, y el botón "Editar" se esconde mientras tanto.
            ->assertSeeHtml('wire:model="diagnostico"')
            ->assertSeeHtml('wire:submit="guardarDiagnostico"')
            ->assertSeeHtml('wire:click="cancelarDiagnostico"')
            ->assertDontSeeHtml('wire:click="editarDiagnostico"');
    }

    public function test_editar_un_diagnostico_que_no_estaba_cargado_abre_el_cuadro_vacio(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->assertSet('diagnostico', '');
    }

    public function test_guardar_el_diagnostico_lo_cambia_deja_la_entrada_y_avisa_al_historial(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->set('diagnostico', '  Trastorno específico del lenguaje  ') // Los espacios de las puntas se quitan.
            ->call('guardarDiagnostico')
            ->assertHasNoErrors()
            // Se cierra el cuadro y se ve el texto nuevo.
            ->assertSet('editandoDiagnostico', false)
            ->assertSeeHtml('Trastorno específico del lenguaje')
            // El aviso para que el historial (el otro componente) muestre la entrada nueva.
            ->assertDispatched('paciente-actualizado');

        $this->assertSame('Trastorno específico del lenguaje', $paciente->fresh()->diagnostico);

        // La entrada, con lo que decía antes, a nombre de quien lo cambió.
        $this->assertSame(
            [['campo' => 'Diagnóstico', 'antes' => 'Retraso del lenguaje', 'ahora' => 'Trastorno específico del lenguaje']],
            $this->cambiosDeLaUnicaEntrada(),
        );
        $this->assertSame($lucia->id, $this->entradas()->sole()->user_id);

        // Solo se tocó el diagnóstico: el equipo sigue igual.
        $this->assertSame([$lucia->id, $marta->id], $this->equipoDe($paciente));
    }

    public function test_guardar_el_diagnostico_sin_cambiarlo_no_deja_entrada(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->call('guardarDiagnostico')
            ->assertHasNoErrors()
            ->assertSet('editandoDiagnostico', false);

        $this->assertDatabaseCount('mensajes', 0);
        $this->assertSame('Retraso del lenguaje', $paciente->fresh()->diagnostico);
    }

    public function test_guardar_el_diagnostico_vacio_lo_deja_en_null_y_lo_anota(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->set('diagnostico', "  \n ") // Solo espacios y un salto de línea: cuenta como vacío.
            ->call('guardarDiagnostico')
            ->assertHasNoErrors()
            ->assertSeeHtml('Sin diagnóstico cargado.');

        // En la base queda null, no un texto vacío.
        $this->assertNull($paciente->fresh()->diagnostico);
        $this->assertSame([['campo' => 'Diagnóstico', 'antes' => 'Retraso del lenguaje', 'ahora' => null]], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_cargar_un_diagnostico_que_no_habia_anota_null_como_valor_anterior(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertSee('Sin diagnóstico cargado.')
            ->call('editarDiagnostico')
            ->set('diagnostico', 'Retraso del lenguaje')
            ->call('guardarDiagnostico')
            ->assertHasNoErrors();

        $this->assertSame([['campo' => 'Diagnóstico', 'antes' => null, 'ahora' => 'Retraso del lenguaje']], $this->cambiosDeLaUnicaEntrada());
    }

    public function test_un_diagnostico_de_mas_de_5000_caracteres_da_error_y_no_guarda(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->set('diagnostico', str_repeat('a', 5001))
            ->call('guardarDiagnostico')
            ->assertHasErrors(['diagnostico' => 'max'])
            ->assertSeeHtml('El texto es demasiado largo.')
            // Con errores el cuadro sigue abierto, para corregir.
            ->assertSet('editandoDiagnostico', true)
            ->assertNotDispatched('paciente-actualizado');

        $this->assertSame('Retraso del lenguaje', $paciente->fresh()->diagnostico);
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_un_diagnostico_de_5000_caracteres_justos_se_guarda(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->set('diagnostico', str_repeat('a', 5000))
            ->call('guardarDiagnostico')
            ->assertHasNoErrors();

        $this->assertSame(5000, mb_strlen($paciente->fresh()->diagnostico));
    }

    public function test_cancelar_cierra_el_cuadro_sin_guardar_ni_dejar_entrada(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->set('diagnostico', 'Texto que no se quiere guardar')
            ->call('cancelarDiagnostico')
            ->assertSet('editandoDiagnostico', false)
            ->assertSeeHtml('Retraso del lenguaje')
            // Si se vuelve a abrir, arranca otra vez con lo guardado y no con lo que se había escrito.
            ->call('editarDiagnostico')
            ->assertSet('diagnostico', 'Retraso del lenguaje');

        $this->assertSame('Retraso del lenguaje', $paciente->fresh()->diagnostico);
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_direccion_que_es_del_equipo_tambien_puede_editar_el_diagnostico(): void
    {
        $paciente = $this->crearPaciente();
        $direccion = $this->entrarComoDireccion();
        $paciente->profesionales()->attach($direccion->id);

        Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->assertSeeHtml('wire:click="editarDiagnostico"')
            ->call('editarDiagnostico')
            ->set('diagnostico', 'Retraso del lenguaje')
            ->call('guardarDiagnostico')
            ->assertHasNoErrors();

        $this->assertSame('Retraso del lenguaje', $paciente->fresh()->diagnostico);
        $this->assertSame($direccion->id, $this->entradas()->sole()->user_id);
    }

    // ---------------------------------------------------------------- Ficha: quien no es del equipo no edita el diagnóstico

    public function test_direccion_fuera_del_equipo_ve_el_diagnostico_pero_no_el_boton_para_editarlo(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);
        $this->crearProfesionalDelEquipo($paciente);
        $this->entrarComoDireccion(); // Ve la ficha de todos, pero no está en este equipo.

        Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->assertOk()
            ->assertViewHas('esDelEquipo', false)
            ->assertSee('Retraso del lenguaje')
            ->assertDontSeeHtml('wire:click="editarDiagnostico"')
            ->assertDontSeeHtml('wire:model="diagnostico"');
    }

    public function test_direccion_fuera_del_equipo_recibe_403_al_querer_editar_o_guardar_el_diagnostico(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);
        $this->crearProfesionalDelEquipo($paciente);
        $this->entrarComoDireccion();

        // Aunque la vista no le muestre el botón, los métodos se podrían llamar igual: tienen que rechazarlo.
        // Se abre una ficha para cada intento: después de un 403 la pantalla queda inutilizada.
        Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->assertForbidden();

        Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->set('diagnostico', 'Cambiado por alguien de afuera')
            ->call('guardarDiagnostico')
            ->assertForbidden();

        $this->assertSame('Retraso del lenguaje', $paciente->fresh()->diagnostico);
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_un_profesional_al_que_sacaron_del_equipo_con_la_ficha_abierta_ya_no_puede_guardar_el_diagnostico(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $ficha = Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->call('editarDiagnostico')
            ->set('diagnostico', 'Lo escribió cuando todavía era del equipo');

        // Mientras tenía el cuadro abierto, Dirección la sacó del equipo.
        $paciente->profesionales()->detach($lucia->id);

        // El permiso se vuelve a comprobar al guardar, no solo al abrir.
        $ficha->call('guardarDiagnostico')->assertForbidden();

        $this->assertSame('Retraso del lenguaje', $paciente->fresh()->diagnostico);
        $this->assertDatabaseCount('mensajes', 0);
    }

    // ---------------------------------------------------------------- Historial: cómo se ve una entrada

    public function test_la_entrada_se_ve_en_el_historial_con_quien_cambio_los_datos_la_hora_y_cada_cambio(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo', 'obra_social' => 'OSDE']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');

        $this->travelTo('2026-10-08 10:15:00');
        $paciente->actualizar(['grado' => 'Tercero', 'obra_social' => null], null, $lucia);

        $html = Livewire::actingAs($marta)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder([
                '— hoy —',
                'Actualización', 'Lucía Pérez', 'cambió los datos · 10:15',
                // Textos cortos: en un renglón, lo anterior y lo nuevo con una flecha en el medio.
                'Obra social:', 'OSDE', '→', '—', // Lo que quedó vacío se muestra con una raya.
                'Grado:', 'Segundo', '→', 'Tercero',
            ])
            ->assertDontSee('Todavía no hay mensajes.')
            ->html();

        // Nunca se muestra el texto crudo (la lista en JSON), ni escapado ni sin escapar.
        $this->assertStringNotContainsString('"campo"', $html);
        $this->assertStringNotContainsString('campo&quot;', $html);
        // Lo anterior va tachado.
        $this->assertMatchesRegularExpression('/<s [^>]*>Segundo<\/s>/', $html);
    }

    public function test_los_textos_largos_se_muestran_en_dos_renglones_antes_y_ahora(): void
    {
        $antes = 'Retraso del lenguaje con dificultades en la comprensión de consignas.';
        $ahora = 'Trastorno específico del lenguaje, en seguimiento con fonoaudiología.';
        $paciente = $this->crearPaciente(['diagnostico' => $antes]);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');

        $paciente->actualizar(['diagnostico' => $ahora], null, $lucia);

        $html = Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Diagnóstico', 'Antes:', $antes, 'Ahora:', $ahora])
            ->html();

        // En este formato no hay flecha ni tachado.
        $this->assertStringNotContainsString('→', $html);
        $this->assertStringNotContainsString('<s ', $html);
    }

    public function test_un_texto_largo_que_antes_estaba_vacio_muestra_una_raya_en_antes(): void
    {
        $ahora = 'Trastorno específico del lenguaje, en seguimiento con fonoaudiología.';
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $paciente->actualizar(['diagnostico' => $ahora], null, $lucia);

        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Antes:', '—', 'Ahora:', $ahora]);
    }

    public function test_los_cambios_de_equipo_se_muestran_con_su_texto(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $pedro = User::factory()->create(['name' => 'Pedro Ruiz']);

        $paciente->actualizar([], [$lucia->id, $pedro->id], $lucia);

        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Equipo.', 'Se sumó: Pedro Ruiz', 'Equipo.', 'Salió: Marta Díaz']);
    }

    public function test_la_entrada_lleva_la_etiqueta_nuevo_para_quien_no_la_vio_y_no_para_quien_hizo_el_cambio(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);

        // Para la autora no es novedad.
        $html = Livewire::actingAs($lucia)->test(Mensajes::class, ['paciente' => $paciente])->html();
        $this->assertSame(0, substr_count($html, 'Nuevo'));

        // Marta todavía no había abierto el historial.
        $html = Livewire::actingAs($marta)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['cambió los datos', 'Nuevo', 'Grado:'])
            ->html();
        $this->assertSame(1, substr_count($html, 'Nuevo'));
    }

    public function test_la_entrada_va_en_orden_con_los_mensajes_segun_su_fecha(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $this->travelTo('2026-10-08 09:00:00');
        Mensaje::create(['paciente_id' => $paciente->id, 'user_id' => $lucia->id, 'tipo' => 'mensaje', 'cuerpo' => 'Antes del cambio.']);
        $this->travelTo('2026-10-08 10:00:00');
        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);
        $this->travelTo('2026-10-08 11:00:00');
        Mensaje::create(['paciente_id' => $paciente->id, 'user_id' => $lucia->id, 'tipo' => 'mensaje', 'cuerpo' => 'Después del cambio.']);

        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Antes del cambio.', 'cambió los datos · 10:00', 'Después del cambio.']);
    }

    public function test_el_filtro_actualizacion_muestra_solo_las_entradas_de_la_bitacora(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        Mensaje::create(['paciente_id' => $paciente->id, 'user_id' => $lucia->id, 'tipo' => 'mensaje', 'cuerpo' => 'Un mensaje comun.']);
        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);

        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            // Sin filtro se ven los dos.
            ->assertSee('Un mensaje comun.')
            ->assertSee('cambió los datos')
            ->set('filtro', 'actualizacion')
            ->assertSeeHtml('cambió los datos')
            ->assertDontSeeHtml('Un mensaje comun.')
            // Y al filtrar por "Mensaje" es al revés.
            ->set('filtro', 'mensaje')
            ->assertSeeHtml('Un mensaje comun.')
            ->assertDontSeeHtml('cambió los datos');
    }

    public function test_el_historial_escucha_el_aviso_de_la_ficha_y_muestra_la_entrada_recien_creada(): void
    {
        $paciente = $this->crearPaciente(['grado' => 'Segundo']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $historial = Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSee('Todavía no hay mensajes.');

        // Se cambia un dato (como hace la ficha con el diagnóstico) y la ficha avisa.
        $paciente->actualizar(['grado' => 'Tercero'], null, $lucia);

        $historial->dispatch('paciente-actualizado')
            ->assertSeeHtml('cambió los datos')
            ->assertSeeHtml('Tercero')
            ->assertDontSeeHtml('Todavía no hay mensajes.');
    }

    public function test_quien_no_es_del_equipo_no_ve_las_entradas_de_la_bitacora(): void
    {
        $paciente = $this->crearPaciente(['diagnostico' => 'Retraso del lenguaje']);
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $paciente->actualizar(['diagnostico' => 'Otro diagnóstico'], null, $lucia);
        $this->entrarComoDireccion(); // Hasta Dirección: si no está en el equipo, no ve el historial.

        Livewire::test(Mensajes::class, ['paciente' => $paciente])
            ->assertSee('Solo el equipo de este paciente puede ver el historial.')
            ->assertDontSee('cambió los datos')
            // Tampoco lo que decía antes el diagnóstico, que solo está en la entrada.
            ->assertDontSee('Retraso del lenguaje');
    }
}
