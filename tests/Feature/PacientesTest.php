<?php

namespace Tests\Feature;

use App\Livewire\Pacientes\Ficha;
use App\Livewire\Pacientes\Formulario;
use App\Livewire\Pacientes\Lista;
use App\Models\Disciplina;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de las pantallas de pacientes: el listado administrativo (con su buscador, filtros y orden),
 * la ficha, crear y editar (no hay borrar).
 *
 * Después de una acción (call o set) el orden se comprueba con assertSeeHtmlInOrder y no con assertSeeInOrder:
 * este último mira la respuesta cruda de Livewire, que tras una acción viene como JSON con las tildes
 * escritas en código ("G\u00f3mez"), y entonces no encuentra los nombres con tilde.
 */
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

    /**
     * Abre el formulario de alta (como Dirección) con los cuatro obligatorios ya cargados;
     * $campos pisa los que se quieran probar.
     */
    private function formularioCompleto(array $campos = [])
    {
        $this->entrarComoDireccion(); // Solo Dirección carga pacientes.

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

    /**
     * Abre el formulario de edición de un paciente con la sesión de alguien de Dirección (el único rol que edita).
     */
    private function formularioDeEdicion(Paciente $paciente): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Formulario::class, ['paciente' => $paciente]);
    }

    /**
     * Abre la ficha de un paciente con la sesión de alguien de Dirección, que ve la de todos.
     * (Quién más puede abrirla, y quién no, se prueba en RolesTest.)
     */
    private function ficha(Paciente $paciente): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Ficha::class, ['paciente' => $paciente]);
    }

    /** Abre la lista de pacientes con la sesión de alguien de Dirección, que los ve a todos. */
    private function lista(): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Lista::class);
    }

    /**
     * Carga cuatro pacientes para probar el buscador, los filtros y el orden, y abre la lista como Dirección.
     * Los datos van cruzados a propósito: ninguna columna ordena igual que otra.
     *
     *   Álvarez, Carla   38.000.111   sin fecha de ingreso   inactiva   sin equipo
     *   Arnal, Bruno     40.111.222   ingresó 02/04/2024     inactivo   Lucía y Marta
     *   Gómez, Íñigo     45.333.444   ingresó 01/03/2021     activo     Marta
     *   Zapata, Ana      52.118.304   ingresó 10/02/2025     activa     Lucía
     *
     * La lista abre mostrando solo los activos. Con $estado = 'todos' (lo normal en estos tests) se cambia
     * el filtro para tener a los cuatro a la vista; con 'activos' se deja tal como abre.
     */
    private function listaConCuatroPacientes(string $estado = 'todos'): Testable
    {
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $marta = User::factory()->create(['name' => 'Marta Díaz']);

        $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304', 'fecha_ingreso' => '2025-02-10'])
            ->profesionales()->attach($lucia->id);
        $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222', 'fecha_ingreso' => '2024-04-02', 'activo' => false])
            ->profesionales()->attach([$lucia->id, $marta->id]);
        $this->crearPaciente(['nombre' => 'Íñigo', 'apellido' => 'Gómez', 'dni' => '45333444', 'fecha_ingreso' => '2021-03-01'])
            ->profesionales()->attach($marta->id);
        $this->crearPaciente(['nombre' => 'Carla', 'apellido' => 'Álvarez', 'dni' => '38000111', 'activo' => false]);

        $lista = $this->lista();

        return $estado === 'activos' ? $lista : $lista->set('estado', $estado);
    }

    /** Comprueba que en la lista estén exactamente esos pacientes ("Apellido, Nombre") y ninguno más de los cuatro. */
    private function assertSoloSeVen(Testable $lista, array $esperados): void
    {
        foreach (['Álvarez, Carla', 'Arnal, Bruno', 'Gómez, Íñigo', 'Zapata, Ana'] as $paciente) {
            in_array($paciente, $esperados, true) ? $lista->assertSee($paciente) : $lista->assertDontSee($paciente);
        }
    }

    // ---------------------------------------------------------------- Rutas

    public function test_sin_sesion_las_pantallas_de_pacientes_redirigen_al_login(): void
    {
        $paciente = $this->crearPaciente();

        $this->get('/administracion/pacientes')->assertRedirect('/login');
        $this->get('/administracion/pacientes/crear')->assertRedirect('/login');
        $this->get("/administracion/pacientes/{$paciente->id}/editar")->assertRedirect('/login');
        // La ficha no es de administración: sigue en /pacientes/{id}.
        $this->get("/pacientes/{$paciente->id}")->assertRedirect('/login');
    }

    public function test_con_sesion_la_lista_de_pacientes_se_muestra(): void
    {
        // El listado administrativo es solo de Dirección (un profesional recibe 403, ver RolesTest).
        $this->entrarComoDireccion();

        $this->get('/administracion/pacientes')
            ->assertOk()
            ->assertSeeLivewire(Lista::class);
    }

    public function test_direccion_ve_la_pantalla_de_crear_paciente(): void
    {
        $this->entrarComoDireccion();

        // "crear" no se tiene que confundir con el id de un paciente.
        $this->get('/administracion/pacientes/crear')
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            // El formulario ya no lleva título: se reconoce por el link de volver al listado...
            ->assertSeeInOrder(['href="'.route('pacientes').'"', 'Volver a pacientes'], false)
            // ...y es el de alta porque no tiene la casilla de "Activo", que solo va al editar.
            ->assertDontSee('Destildar para darlo de baja');
    }

    public function test_direccion_ve_la_ficha_del_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $this->entrarComoDireccion();

        $this->get("/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSeeLivewire(Ficha::class);
    }

    public function test_direccion_ve_la_pantalla_de_editar_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $this->entrarComoDireccion();

        $this->get("/administracion/pacientes/{$paciente->id}/editar")
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            // El formulario ya no lleva título: se reconoce por el link de volver al listado...
            ->assertSeeInOrder(['href="'.route('pacientes').'"', 'Volver a pacientes'], false)
            // ...y es el de edición porque tiene la casilla de "Activo", que no va al crear.
            ->assertSee('Destildar para darlo de baja');
    }

    public function test_la_ficha_de_un_paciente_que_no_existe_da_404(): void
    {
        $this->entrarComoDireccion();

        $this->get('/pacientes/9999')->assertNotFound();
    }

    public function test_editar_un_paciente_que_no_existe_da_404(): void
    {
        $this->entrarComoDireccion();

        $this->get('/administracion/pacientes/9999/editar')->assertNotFound();
    }

    public function test_las_direcciones_viejas_de_pacientes_ya_no_existen(): void
    {
        $paciente = $this->crearPaciente();
        $this->entrarComoDireccion();

        // El listado y el formulario se mudaron a /administracion/pacientes. En /pacientes quedó solo la ficha.
        $this->get('/pacientes')->assertNotFound();
        $this->get('/pacientes/crear')->assertNotFound();
        $this->get("/pacientes/{$paciente->id}/editar")->assertNotFound();
    }

    // ---------------------------------------------------------------- Lista

    public function test_la_lista_muestra_los_pacientes_ordenados_por_apellido_con_dni_y_estado(): void
    {
        // Se cargan desordenados a propósito: la lista los tiene que ordenar por apellido.
        $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304', 'activo' => false]);
        $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222', 'activo' => true]);

        // La lista abre solo con los activos: para ver también a Ana hay que pedir todos.
        $this->lista()
            ->set('estado', 'todos')
            ->assertSeeHtmlInOrder([
                'Arnal, Bruno', '40.111.222', '>Activo</span>',
                'Zapata, Ana', '52.118.304', '>Inactivo</span>',
            ])
            ->assertDontSee('No hay pacientes para mostrar.');
    }

    public function test_la_lista_muestra_el_dni_y_la_edad_debajo_del_nombre(): void
    {
        $this->travelTo('2026-10-08 12:00:00'); // Fecha fija, para que la edad no dependa del día.
        $this->crearPaciente(['fecha_nacimiento' => '2018-10-09']); // Cumple mañana: todavía tiene 7.

        // Ya no son columnas: van juntos en un renglón chico debajo del nombre.
        $this->lista()->assertSeeInOrder(['Zapata, Ana', 'DNI 52.118.304 · 7 años']);
    }

    public function test_la_lista_muestra_la_fecha_de_ingreso_o_una_raya_si_no_esta_cargada(): void
    {
        $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222', 'fecha_ingreso' => '2024-04-02']);
        $this->crearPaciente(); // Ana, sin fecha de ingreso (es opcional).

        $this->lista()->assertSeeInOrder([
            'Ingreso', // El título de la columna.
            'Arnal, Bruno', '02/04/2024',
            'Zapata, Ana', '—',
        ]);
    }

    public function test_al_lado_del_titulo_dice_cuantos_pacientes_hay_en_la_tabla(): void
    {
        $lista = $this->listaConCuatroPacientes('activos');

        // La etiqueta lleva solo el número (ya no dice "2 pacientes"). Cuenta lo que se ve: abre con los dos activos.
        $lista->assertSeeHtml('text-brand">2</span>');
        // Y cambia con los filtros y el buscador.
        $lista->set('estado', 'todos')->assertSeeHtml('text-brand">4</span>');
        $lista->set('buscar', 'zapata')->assertSeeHtml('text-brand">1</span>');
        $lista->set('buscar', 'nadie-se-llama-asi')->assertSeeHtml('text-brand">0</span>');
    }

    public function test_la_lista_tiene_el_link_para_cargar_un_paciente_nuevo(): void
    {
        // Va dentro de la tarjeta, al lado del título: el link y enseguida su texto.
        $this->lista()
            ->assertSeeInOrder(['Pacientes', 'href="'.route('pacientes.crear').'"', '+ Nuevo'], false);
    }

    public function test_el_nombre_en_la_lista_lleva_directo_a_editar_no_a_la_ficha(): void
    {
        $paciente = $this->crearPaciente();

        $html = $this->lista()->html();

        // Con comillas, para no confundir el link a la ficha con el de editar (uno es prefijo del otro).
        $this->assertStringContainsString('href="'.route('pacientes.editar', $paciente).'"', $html);
        $this->assertStringNotContainsString('href="'.route('pacientes.ver', $paciente).'"', $html);
    }

    public function test_la_lista_muestra_los_nombres_del_equipo_de_cada_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $marta = User::factory()->create(['name' => 'Marta Díaz']);
        User::factory()->create(['name' => 'Pedro Suelto']); // No atiende a nadie: no tiene que aparecer.
        $paciente->profesionales()->sync([$lucia->id, $marta->id]);

        $html = $this->lista()
            // En la columna Equipo va una etiqueta por profesional (después del nombre del paciente; antes,
            // esos mismos nombres están en el desplegable del filtro).
            ->assertSeeInOrder(['Zapata, Ana', 'Lucía Pérez'])
            ->assertSeeInOrder(['Zapata, Ana', 'Marta Díaz'])
            ->html();

        // Cada una aparece dos veces: en el desplegable del filtro por profesional y en su etiqueta del equipo.
        $this->assertSame(2, substr_count($html, 'Lucía Pérez'));
        $this->assertSame(2, substr_count($html, 'Marta Díaz'));
        // Pedro aparece una sola vez: en el desplegable (que lista a todos), no en un equipo.
        $this->assertSame(1, substr_count($html, 'Pedro Suelto'));
        $this->assertStringContainsString('>Pedro Suelto</option>', $html);
    }

    public function test_la_etiqueta_de_cada_profesional_lleva_un_puntito_del_color_de_cada_disciplina_suya(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología', 'color' => '#ff0000']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología', 'color' => '#00aa55']);
        Disciplina::create(['nombre' => 'Psicomotricidad', 'color' => '#123456']); // Nadie del equipo la tiene.
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $lucia->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]); // Tiene dos: lleva dos puntitos.
        $marta = User::factory()->create(['name' => 'Marta Díaz']); // Sin disciplina: sin puntito.
        $paciente->profesionales()->sync([$lucia->id, $marta->id]);

        $html = $this->lista()
            // El color viene de la base, por eso va en style. El nombre de la disciplina queda en el title.
            ->assertSeeHtml('style="background-color: #ff0000" title="Psicología"')
            ->assertSeeHtml('style="background-color: #00aa55" title="Fonoaudiología"')
            ->html();

        // Dos puntitos en total, los dos de Lucía.
        $this->assertSame(2, substr_count($html, 'style="background-color:'));
        $this->assertStringNotContainsString('#123456', $html);
    }

    public function test_un_paciente_sin_equipo_muestra_una_raya(): void
    {
        $this->crearPaciente(['fecha_ingreso' => '2024-04-02']); // Con ingreso, para que la única raya sea la del equipo.

        $html = $this->lista()->assertSeeInOrder(['Zapata, Ana', '52.118.304', '02/04/2024', '—'])->html();

        $this->assertSame(1, substr_count($html, '—'));
    }

    public function test_sin_pacientes_la_lista_avisa_que_esta_vacia(): void
    {
        $this->lista()->assertSee('No hay pacientes para mostrar.');
    }

    // ---------------------------------------------------------------- Lista: buscador

    public function test_el_buscador_encuentra_por_nombre_o_apellido_sin_importar_tildes_ni_mayusculas(): void
    {
        $lista = $this->listaConCuatroPacientes()->assertSet('buscar', '');

        // Sin tilde y en mayúsculas encuentra a "Gómez".
        $this->assertSoloSeVen($lista->set('buscar', 'GOMEZ'), ['Gómez, Íñigo']);
        // Por el nombre, sin la tilde ni la eñe.
        $this->assertSoloSeVen($lista->set('buscar', 'inigo'), ['Gómez, Íñigo']);
        // Escrito con tilde encuentra igual.
        $this->assertSoloSeVen($lista->set('buscar', 'álvarez'), ['Álvarez, Carla']);
        // "ar" está en Álvarez, Arnal y Carla... y no en los otros dos.
        $this->assertSoloSeVen($lista->set('buscar', 'AR'), ['Álvarez, Carla', 'Arnal, Bruno']);
        // Con el buscador vacío vuelven todos.
        $this->assertSoloSeVen($lista->set('buscar', ''), ['Álvarez, Carla', 'Arnal, Bruno', 'Gómez, Íñigo', 'Zapata, Ana']);
    }

    public function test_el_buscador_acepta_el_nombre_escrito_de_las_dos_formas(): void
    {
        $lista = $this->listaConCuatroPacientes();

        // "Nombre Apellido", como se dice.
        $this->assertSoloSeVen($lista->set('buscar', 'ana zapata'), ['Zapata, Ana']);
        // "Apellido, Nombre", como se ve en la tabla.
        $this->assertSoloSeVen($lista->set('buscar', 'zapata, ana'), ['Zapata, Ana']);
    }

    public function test_el_buscador_encuentra_por_dni_con_o_sin_puntos(): void
    {
        $lista = $this->listaConCuatroPacientes();

        // Sin puntos, como está guardado.
        $this->assertSoloSeVen($lista->set('buscar', '40111222'), ['Arnal, Bruno']);
        // Con puntos, como se ve en la tabla.
        $this->assertSoloSeVen($lista->set('buscar', '40.111.222'), ['Arnal, Bruno']);
        // Y también con solo una parte, de las dos formas.
        $this->assertSoloSeVen($lista->set('buscar', '4011'), ['Arnal, Bruno']);
        $this->assertSoloSeVen($lista->set('buscar', '40.11'), ['Arnal, Bruno']);
        $this->assertSoloSeVen($lista->set('buscar', '333.444'), ['Gómez, Íñigo']);
    }

    public function test_si_la_busqueda_no_encuentra_nada_la_lista_lo_avisa(): void
    {
        $lista = $this->listaConCuatroPacientes()
            ->assertDontSee('No hay pacientes para mostrar.')
            ->set('buscar', '99999999')
            ->assertSee('No hay pacientes para mostrar.');

        $this->assertSoloSeVen($lista, []);
    }

    // ---------------------------------------------------------------- Lista: filtros

    public function test_el_filtro_de_estado_separa_activos_e_inactivos(): void
    {
        // Abre mostrando solo los activos: los dados de baja no molestan en el trabajo de todos los días.
        $lista = $this->listaConCuatroPacientes('activos')->assertSet('estado', 'activos');
        $this->assertSoloSeVen($lista, ['Gómez, Íñigo', 'Zapata, Ana']);

        $this->assertSoloSeVen($lista->set('estado', 'inactivos'), ['Álvarez, Carla', 'Arnal, Bruno']);
        // Con "todos" se llega también a los dados de baja.
        $this->assertSoloSeVen($lista->set('estado', 'todos'), ['Álvarez, Carla', 'Arnal, Bruno', 'Gómez, Íñigo', 'Zapata, Ana']);
        $this->assertSoloSeVen($lista->set('estado', 'activos'), ['Gómez, Íñigo', 'Zapata, Ana']);
    }

    public function test_el_filtro_por_profesional_muestra_solo_los_pacientes_que_atiende(): void
    {
        $lista = $this->listaConCuatroPacientes()->assertSet('profesional', '');
        $lucia = User::where('name', 'Lucía Pérez')->firstOrFail();
        $marta = User::where('name', 'Marta Díaz')->firstOrFail();

        // El desplegable manda el id como texto.
        $this->assertSoloSeVen($lista->set('profesional', (string) $lucia->id), ['Arnal, Bruno', 'Zapata, Ana']);
        $this->assertSoloSeVen($lista->set('profesional', (string) $marta->id), ['Arnal, Bruno', 'Gómez, Íñigo']);
        // Vacío = "Todos los profesionales": vuelve también Carla, que no tiene equipo.
        $this->assertSoloSeVen($lista->set('profesional', ''), ['Álvarez, Carla', 'Arnal, Bruno', 'Gómez, Íñigo', 'Zapata, Ana']);
    }

    public function test_el_filtro_por_un_profesional_que_no_atiende_a_nadie_deja_la_lista_vacia(): void
    {
        $lista = $this->listaConCuatroPacientes();
        $direccion = auth()->user(); // Quien tiene la sesión no está en ningún equipo.

        $lista->set('profesional', (string) $direccion->id)->assertSee('No hay pacientes para mostrar.');
        $this->assertSoloSeVen($lista, []);
    }

    public function test_el_buscador_y_los_filtros_se_combinan(): void
    {
        $lista = $this->listaConCuatroPacientes();
        $lucia = User::where('name', 'Lucía Pérez')->firstOrFail();

        // De Lucía (Arnal y Zapata) + activos: queda solo Zapata.
        $lista->set('profesional', (string) $lucia->id)->set('estado', 'activos');
        $this->assertSoloSeVen($lista, ['Zapata, Ana']);

        // Y si además se busca a Arnal, que es de Lucía pero está inactivo, no queda ninguno.
        $this->assertSoloSeVen($lista->set('buscar', 'arnal'), []);
    }

    // ---------------------------------------------------------------- Lista: orden

    public function test_la_lista_abre_ordenada_por_paciente_y_un_clic_en_la_columna_invierte_el_sentido(): void
    {
        $this->listaConCuatroPacientes()
            ->assertSet('orden', 'paciente')
            ->assertSet('ascendente', true)
            // "Álvarez" lleva tilde: tiene que ordenarse en la A, antes que "Arnal", y no después de la Z.
            ->assertSeeHtmlInOrder(['Álvarez, Carla', 'Arnal, Bruno', 'Gómez, Íñigo', 'Zapata, Ana'])
            ->call('ordenarPor', 'paciente')
            ->assertSet('orden', 'paciente')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Gómez, Íñigo', 'Arnal, Bruno', 'Álvarez, Carla'])
            // Un tercer clic vuelve al sentido original.
            ->call('ordenarPor', 'paciente')
            ->assertSet('ascendente', true)
            ->assertSeeHtmlInOrder(['Álvarez, Carla', 'Arnal, Bruno', 'Gómez, Íñigo', 'Zapata, Ana']);
    }

    public function test_la_lista_se_puede_ordenar_por_fecha_de_ingreso(): void
    {
        $this->listaConCuatroPacientes()
            ->call('ordenarPor', 'ingreso')
            ->assertSet('orden', 'ingreso')
            // Una columna nueva arranca de menor a mayor: del ingreso más viejo al más nuevo.
            // Carla no tiene la fecha cargada: va antes que todos.
            ->assertSet('ascendente', true)
            ->assertSeeHtmlInOrder(['Álvarez, Carla', 'Gómez, Íñigo', 'Arnal, Bruno', 'Zapata, Ana'])
            ->call('ordenarPor', 'ingreso')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Arnal, Bruno', 'Gómez, Íñigo', 'Álvarez, Carla']);
    }

    public function test_la_lista_se_puede_ordenar_por_estado_y_los_empates_quedan_por_nombre(): void
    {
        $this->listaConCuatroPacientes()
            ->call('ordenarPor', 'estado')
            ->assertSet('orden', 'estado')
            ->assertSet('ascendente', true)
            // Primero los activos. Dentro de cada estado hay empate: desempata el nombre, sin contar las tildes
            // (Gómez antes que Zapata, y "Álvarez" antes que "Arnal").
            ->assertSeeHtmlInOrder(['Gómez, Íñigo', 'Zapata, Ana', 'Álvarez, Carla', 'Arnal, Bruno'])
            ->call('ordenarPor', 'estado')
            ->assertSet('ascendente', false)
            // Al revés: primero los inactivos. Los empates siguen de la A a la Z.
            ->assertSeeHtmlInOrder(['Álvarez, Carla', 'Arnal, Bruno', 'Gómez, Íñigo', 'Zapata, Ana']);
    }

    public function test_los_pacientes_sin_fecha_de_ingreso_empatan_y_quedan_por_nombre(): void
    {
        // Se cargan al revés del orden alfabético, para que no queden bien por casualidad.
        $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304']);
        $this->crearPaciente(['nombre' => 'Íñigo', 'apellido' => 'Gómez', 'dni' => '45333444']);
        $this->crearPaciente(['nombre' => 'Carla', 'apellido' => 'Álvarez', 'dni' => '38000111']);
        $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222', 'fecha_ingreso' => '2024-04-02']);

        $this->lista()
            ->call('ordenarPor', 'ingreso')
            // Los tres sin fecha van primero y, entre ellos, por nombre. Bruno, el único con fecha, al final.
            ->assertSeeHtmlInOrder(['Álvarez, Carla', 'Gómez, Íñigo', 'Zapata, Ana', 'Arnal, Bruno']);
    }

    public function test_cambiar_de_columna_arranca_de_nuevo_en_ascendente(): void
    {
        $this->listaConCuatroPacientes()
            ->call('ordenarPor', 'ingreso')
            ->call('ordenarPor', 'ingreso') // Queda por ingreso, descendente.
            ->assertSet('ascendente', false)
            ->call('ordenarPor', 'estado')
            ->assertSet('orden', 'estado')
            ->assertSet('ascendente', true);
    }

    public function test_ordenar_por_una_columna_que_no_existe_no_cambia_nada(): void
    {
        // El nombre de la columna viaja desde el navegador: se podría mandar cualquier cosa.
        $this->listaConCuatroPacientes()
            ->call('ordenarPor', 'ingreso')
            ->call('ordenarPor', 'ingreso') // Queda por ingreso, descendente.
            ->call('ordenarPor', 'dni')
            ->assertSet('orden', 'ingreso')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Arnal, Bruno', 'Gómez, Íñigo', 'Álvarez, Carla'])
            // "edad" fue columna de este listado y lo sigue siendo en el Inicio, pero acá ya no se ordena por ella.
            ->call('ordenarPor', 'edad')
            ->assertSet('orden', 'ingreso')
            ->assertSet('ascendente', false)
            // "actividad" es columna en el Inicio, pero en este listado no existe.
            ->call('ordenarPor', 'actividad')
            ->assertSet('orden', 'ingreso')
            ->assertSet('ascendente', false);
    }

    public function test_el_orden_se_mantiene_al_buscar_y_filtrar(): void
    {
        $this->listaConCuatroPacientes()
            ->call('ordenarPor', 'paciente') // Descendente.
            ->set('estado', 'inactivos')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Arnal, Bruno', 'Álvarez, Carla']);
    }

    // ---------------------------------------------------------------- Lista: se recuerda durante la sesión

    public function test_el_buscador_los_filtros_y_el_orden_se_recuerdan_al_volver_a_abrir_la_lista(): void
    {
        $lista = $this->listaConCuatroPacientes('activos');
        $lucia = User::where('name', 'Lucía Pérez')->firstOrFail();

        $lista->set('buscar', 'ar')
            ->set('estado', 'inactivos')
            ->set('profesional', (string) $lucia->id)
            ->call('ordenarPor', 'ingreso')
            ->call('ordenarPor', 'ingreso'); // Queda por ingreso, descendente.

        // Se abre de nuevo en la misma sesión (como al entrar a editar un paciente y volver):
        // la tabla sigue como se la dejó, no vuelve a los valores iniciales.
        $otraVez = Livewire::test(Lista::class)
            ->assertSet('buscar', 'ar')
            ->assertSet('estado', 'inactivos')
            ->assertSet('profesional', (string) $lucia->id)
            ->assertSet('orden', 'ingreso')
            ->assertSet('ascendente', false);

        // Y muestra lo que corresponde a esos filtros: de Lucía, inactivo y con "ar", solo queda Arnal.
        $this->assertSoloSeVen($otraVez, ['Arnal, Bruno']);
    }

    public function test_en_una_sesion_nueva_la_lista_abre_con_los_valores_iniciales(): void
    {
        $this->listaConCuatroPacientes('activos')
            ->set('buscar', 'ar')
            ->set('estado', 'inactivos')
            ->call('ordenarPor', 'estado');

        // Lo recordado vive en la sesión: al cerrarla (salir y volver a entrar) no queda nada.
        session()->flush();

        Livewire::test(Lista::class)
            ->assertSet('buscar', '')
            ->assertSet('estado', 'activos')
            ->assertSet('profesional', '')
            ->assertSet('orden', 'paciente')
            ->assertSet('ascendente', true);
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

        $html = $this->ficha($paciente)
            // Cabecera: iniciales, nombre, estado y el renglón "DNI · edad · grado · escuela".
            ->assertSeeInOrder(['AZ', 'Ana Zapata', 'Activo', 'DNI 52.118.304 · 8 años · Tercero · Escuela 12'])
            // A la derecha, los datos sueltos con su título.
            ->assertSeeInOrder(['Nacimiento', '15/03/2018', 'Ingreso', '02/04/2024', 'Obra social', 'OSDE'])
            ->assertSeeInOrder(['Diagnóstico', 'Retraso del lenguaje'])
            ->assertDontSee('Sin diagnóstico cargado.')
            ->html();

        // Con todos los opcionales cargados no queda ninguna raya.
        $this->assertSame(0, substr_count($html, '—'));
    }

    public function test_la_ficha_muestra_los_opcionales_vacios_con_una_raya_o_su_aviso(): void
    {
        $this->travelTo('2026-10-08 12:00:00');

        $paciente = $this->crearPaciente(['fecha_nacimiento' => '2018-03-15']); // Solo los obligatorios.

        $html = $this->ficha($paciente)
            ->assertSee('DNI 52.118.304 · 8 años')
            // El diagnóstico vacío no lleva raya: lo dice con palabras.
            ->assertSee('Sin diagnóstico cargado.')
            ->html();

        // Una raya por cada dato suelto vacío: ingreso y obra social.
        $this->assertSame(2, substr_count($html, '—'));
        // Sin grado ni escuela el renglón termina en la edad: queda un solo separador, no hay sueltos.
        $this->assertSame(1, substr_count($html, ' · '));
    }

    public function test_la_ficha_de_un_paciente_dado_de_baja_dice_inactivo(): void
    {
        $paciente = $this->crearPaciente(['activo' => false]);

        $this->ficha($paciente)->assertSee('Inactivo');
    }

    public function test_las_iniciales_del_paciente_son_las_de_su_nombre_y_apellido_en_mayuscula(): void
    {
        $this->assertSame('AZ', $this->crearPaciente()->iniciales);
        // Con tilde y en minúscula: toma la letra entera (no medio carácter) y la pasa a mayúscula.
        $this->assertSame('ÍÁ', $this->crearPaciente(['nombre' => 'íñigo', 'apellido' => 'álvarez', 'dni' => '45333444'])->iniciales);
    }

    public function test_la_ficha_muestra_el_equipo_con_una_etiqueta_por_cada_disciplina_del_profesional(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología', 'color' => '#aa0000']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología', 'color' => '#00aa00']);
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $lucia->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]); // Tiene dos.
        $marta = User::factory()->create(['name' => 'Marta Díaz']); // Sin disciplina.
        $paciente->profesionales()->sync([$lucia->id, $marta->id]);

        $html = $this->ficha($paciente)
            // Al lado del nombre, sus disciplinas en orden alfabético (se cargaron al revés a propósito).
            ->assertSeeInOrder(['Lucía Pérez', 'Fonoaudiología', 'Psicología', 'Marta Díaz'])
            // Ya no van juntas en una sola etiqueta.
            ->assertDontSee('Fonoaudiología · Psicología')
            ->assertDontSee('Todavía no tiene profesionales asignados.')
            ->html();

        // Cada disciplina es su propia etiqueta, pintada con su color.
        $this->assertStringContainsString('style="color: #00aa00; background-color: #00aa001a">Fonoaudiología</span>', $html);
        $this->assertStringContainsString('style="color: #aa0000; background-color: #aa00001a">Psicología</span>', $html);
        // Son dos etiquetas en total: Marta no tiene disciplinas, así que no lleva ninguna (ni una vacía).
        // Se cuentan por el "1a" del fondo clarito, que solo tienen las etiquetas (los círculos de iniciales no).
        $this->assertSame(2, substr_count($html, '1a">'));
    }

    public function test_la_ficha_tiene_el_link_para_volver_a_mis_pacientes(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create();
        $paciente->profesionales()->attach($lucia->id);

        // Apunta al Inicio ("Mis pacientes"), que es desde donde se abre la ficha y al que entra cualquier usuario.
        Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['href="'.route('inicio').'"', 'Volver a mis pacientes'], false);

        // A Dirección le sale el mismo link: tampoco vuelve al listado administrativo.
        $this->ficha($paciente)
            ->assertSeeInOrder(['href="'.route('inicio').'"', 'Volver a mis pacientes'], false);
    }

    public function test_la_ficha_no_tiene_link_al_listado_administrativo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create();
        $paciente->profesionales()->attach($lucia->id);

        // El link de volver va al Inicio, no al listado de pacientes, que a un profesional le da 403.
        Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertDontSeeHtml('href="'.route('pacientes').'"');
    }

    public function test_la_ficha_no_tiene_link_para_editar_el_paciente_ni_para_direccion(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = User::factory()->create();
        $paciente->profesionales()->attach($lucia->id);

        Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertDontSee('Editar paciente')
            ->assertDontSeeHtml(route('pacientes.editar', $paciente));

        // Dirección edita entrando por Administración › Pacientes, no desde la ficha.
        $this->ficha($paciente)
            ->assertDontSee('Editar paciente')
            ->assertDontSeeHtml(route('pacientes.editar', $paciente));
    }

    public function test_la_ficha_avisa_cuando_el_paciente_no_tiene_equipo(): void
    {
        $paciente = $this->crearPaciente();

        $this->ficha($paciente)
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
        $this->entrarComoDireccion();

        Livewire::test(Formulario::class)->assertDontSee('Destildar para darlo de baja');
    }

    public function test_el_formulario_tiene_el_link_para_volver_al_listado(): void
    {
        $this->entrarComoDireccion();

        Livewire::test(Formulario::class)
            ->assertSeeInOrder(['href="'.route('pacientes').'"', 'Volver a pacientes'], false);
    }

    public function test_en_el_formulario_cada_profesional_del_equipo_muestra_sus_disciplinas_con_su_color(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología', 'color' => '#ff0000']);
        User::factory()->create(['name' => 'Lucía Pérez'])->disciplinas()->attach($psicologia->id);
        $this->entrarComoDireccion();

        // Al lado de cada casilla del equipo va la etiqueta de sus disciplinas, pintada con su color:
        // la letra del color y el fondo del mismo color casi transparente (el "1a" del final).
        Livewire::test(Formulario::class)
            ->assertSeeInOrder(['Lucía Pérez', 'style="color: #ff0000; background-color: #ff00001a"', 'Psicología'], false);
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

        // El DNI está cifrado, así que el control no es la regla "unique" sino una comparación hecha en el formulario.
        $this->formularioCompleto(['nombre' => 'Otra', 'dni' => '52118304'])
            ->call('guardar')
            ->assertHasErrors('dni')
            ->assertSee('Ya existe un paciente con ese DNI.');

        $this->assertDatabaseCount('pacientes', 1);
    }

    public function test_un_dni_duplicado_escrito_con_puntos_tambien_da_error(): void
    {
        $this->crearPaciente(['dni' => '52118304']);

        // Sin quitarle los puntos, "52.118.304" pasaría como un DNI distinto.
        $this->formularioCompleto(['nombre' => 'Otra', 'dni' => '52.118.304'])
            ->call('guardar')
            ->assertHasErrors('dni')
            ->assertSee('Ya existe un paciente con ese DNI.');

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

        $this->formularioDeEdicion($paciente)
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
            ->assertSee('Destildar para darlo de baja');
    }

    public function test_editar_un_paciente_sin_opcionales_deja_esos_campos_vacios(): void
    {
        $paciente = $this->crearPaciente();

        // null se convierte en '' para que los campos del formulario queden en blanco.
        $this->formularioDeEdicion($paciente)
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

        $this->formularioDeEdicion($paciente)
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

        // El control de DNI repetido no cuenta al propio paciente que se está editando.
        $this->formularioDeEdicion($paciente)
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

        // El control de DNI repetido solo ignora al que se edita, no a los demás.
        $this->formularioDeEdicion($otro)
            ->set('dni', '52.118.304')
            ->call('guardar')
            ->assertHasErrors('dni')
            ->assertSee('Ya existe un paciente con ese DNI.');

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
        $this->formularioDeEdicion($paciente)
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

        $this->formularioDeEdicion($paciente)
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

        $this->formularioDeEdicion($paciente)
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
