<?php

namespace Tests\Feature;

use App\Livewire\Disciplinas\Formulario as FormularioDisciplina;
use App\Livewire\Pacientes\Ficha;
use App\Livewire\Pacientes\Formulario;
use App\Livewire\Pacientes\Lista;
use App\Livewire\Pacientes\Mensajes;
use App\Livewire\Usuarios\Formulario as FormularioUsuario;
use App\Livewire\Usuarios\Lista as ListaUsuarios;
use App\Models\Disciplina;
use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de los permisos según el rol (ver docs/roles.md).
 * Dirección carga y edita pacientes, usuarios y disciplinas (todo lo que cuelga de /administracion),
 * y ve la ficha de todos los pacientes.
 * Un profesional solo ve los pacientes de su equipo. El historial de mensajes es siempre solo del equipo.
 *
 * Los roles los crea una migración, así que ya existen en cada test.
 * (El campo "Rol" de la pantalla de usuarios se prueba en UsuariosTest.)
 */
class RolesTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    private const AVISO = 'Solo el equipo de este paciente puede ver el historial.';

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

    /** Guarda un mensaje directo en la base, sin pasar por el formulario. */
    private function crearMensaje(Paciente $paciente, User $autor, string $cuerpo): Mensaje
    {
        return Mensaje::create([
            'paciente_id' => $paciente->id,
            'user_id' => $autor->id,
            'tipo' => 'mensaje',
            'cuerpo' => $cuerpo,
        ]);
    }

    // ---------------------------------------------------------------- Factory

    public function test_la_factory_crea_un_profesional_y_con_direccion_crea_uno_de_direccion(): void
    {
        $profesional = User::factory()->create();
        $direccion = User::factory()->direccion()->create();

        // Cada uno queda con un solo rol: el de Dirección no conserva además el de profesional.
        $this->assertSame(['profesional'], $profesional->getRoleNames()->all());
        $this->assertSame('profesional', $profesional->rol);
        $this->assertFalse($profesional->esDireccion());

        $this->assertSame(['direccion'], $direccion->getRoleNames()->all());
        $this->assertSame('direccion', $direccion->rol);
        $this->assertTrue($direccion->esDireccion());
    }

    // ---------------------------------------------------------------- Pantallas solo de Dirección

    /**
     * Todas las direcciones de administración (listados y formularios). Todas son solo de Dirección.
     *
     * @return array<int, string>
     */
    private function urlsDeAdministracion(Paciente $paciente, User $usuario, Disciplina $disciplina): array
    {
        return [
            '/administracion/pacientes',
            '/administracion/pacientes/crear',
            "/administracion/pacientes/{$paciente->id}/editar",
            '/administracion/profesionales',
            '/administracion/profesionales/crear',
            "/administracion/profesionales/{$usuario->id}/editar",
            '/administracion/disciplinas/crear',
            "/administracion/disciplinas/{$disciplina->id}/editar",
        ];
    }

    public function test_un_profesional_recibe_403_en_todas_las_pantallas_de_administracion(): void
    {
        $paciente = $this->crearPaciente();
        // Es del equipo de ese paciente: igual no puede editarlo.
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $this->actingAs($lucia);

        // Ni siquiera puede abrir el formulario para editarse a sí misma.
        foreach ($this->urlsDeAdministracion($paciente, $lucia, $disciplina) as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_un_profesional_que_entra_a_administracion_a_secas_termina_en_un_403(): void
    {
        // /administracion solo redirige al listado de pacientes, y ahí lo frena el permiso.
        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get('/administracion')
            ->assertForbidden();
    }

    public function test_direccion_entra_a_todas_las_pantallas_de_administracion(): void
    {
        $paciente = $this->crearPaciente();
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $direccion = $this->entrarComoDireccion();

        foreach ($this->urlsDeAdministracion($paciente, $direccion, $disciplina) as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_sin_sesion_todas_las_pantallas_de_administracion_redirigen_al_login(): void
    {
        $paciente = $this->crearPaciente();
        $usuario = User::factory()->direccion()->create(); // Existe, pero nadie inició sesión.
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $this->get('/administracion')->assertRedirect('/login');

        foreach ($this->urlsDeAdministracion($paciente, $usuario, $disciplina) as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_administracion_a_secas_redirige_al_listado_de_pacientes(): void
    {
        $this->entrarComoDireccion();

        $this->get('/administracion')->assertRedirect('/administracion/pacientes');
    }

    /*
     * Los tests que siguen prueban las ACCIONES, no la pantalla: quien ya la tenía abierta
     * (o arma el pedido a mano) no tiene que poder guardar sin ser de Dirección.
     * Por eso la pantalla se abre como Dirección y recién después se cambia la sesión a un profesional.
     */

    public function test_un_profesional_no_puede_guardar_un_usuario(): void
    {
        $pantalla = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(FormularioUsuario::class)
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('admin', true); // El caso más grave: fabricarse un usuario de Dirección.

        $this->actingAs(User::factory()->create());

        $pantalla->call('guardar')->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'ana@andar.test']);
    }

    public function test_un_profesional_no_puede_hacerse_administrador(): void
    {
        $lucia = User::factory()->create();

        $pantalla = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(FormularioUsuario::class, ['usuario' => $lucia])
            ->set('admin', true); // Tildar "Es administrador" = rol Dirección.

        $this->actingAs($lucia);

        $pantalla->call('guardar')->assertForbidden();

        $this->assertSame('profesional', $lucia->fresh()->rol);
    }

    public function test_un_profesional_no_puede_guardar_una_disciplina(): void
    {
        $pantalla = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(FormularioDisciplina::class)
            ->set('nombre', 'Psicología');

        $this->actingAs(User::factory()->create());

        $pantalla->call('guardar')->assertForbidden();

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_un_profesional_no_puede_cambiar_el_nombre_de_una_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $pantalla = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(FormularioDisciplina::class, ['disciplina' => $disciplina])
            ->set('nombre', 'Otro nombre');

        $this->actingAs(User::factory()->create());

        $pantalla->call('guardar')->assertForbidden();

        $this->assertSame('Psicología', $disciplina->fresh()->nombre);
    }

    public function test_un_profesional_no_puede_abrir_ni_usar_el_listado_de_profesionales(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $lucia = User::factory()->create();

        Livewire::actingAs($lucia)->test(ListaUsuarios::class)->assertForbidden();

        // Tampoco las acciones de la tabla, si la pantalla había quedado abierta con una sesión de Dirección.
        // Se abre dos veces: después de un 403 la pantalla queda inutilizada y no admite otro pedido.
        $paraOrdenar = Livewire::actingAs(User::factory()->direccion()->create())->test(ListaUsuarios::class);
        $paraFiltrar = Livewire::test(ListaUsuarios::class);

        $this->actingAs($lucia);

        // Ni ordenar ni filtrar: el permiso se vuelve a comprobar en cada pedido.
        $paraOrdenar->call('ordenarPor', 'pacientes')->assertForbidden();
        $paraFiltrar->set('disciplina', (string) $disciplina->id)->assertForbidden();
    }

    public function test_un_profesional_no_puede_guardar_un_paciente_ni_cambiar_su_equipo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $pedro = User::factory()->create(); // No es del equipo: el intento es sumarlo.

        // Alta de un paciente nuevo.
        $alta = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(Formulario::class)
            ->set('nombre', 'Bruno')
            ->set('apellido', 'Arnal')
            ->set('dni', '40111222')
            ->set('fecha_nacimiento', '2018-10-08');

        // Edición de uno existente, del que Lucía es parte del equipo.
        $edicion = Livewire::test(Formulario::class, ['paciente' => $paciente])
            ->set('nombre', 'Otro nombre')
            ->set('equipo', [(string) $lucia->id, (string) $pedro->id]);

        $this->actingAs($lucia);

        $alta->call('guardar')->assertForbidden();
        $edicion->call('guardar')->assertForbidden();

        $this->assertDatabaseCount('pacientes', 1); // Solo el que ya estaba.
        $this->assertSame('Ana', $paciente->fresh()->nombre);
        $this->assertDatabaseCount('paciente_user', 1);
        $this->assertDatabaseMissing('paciente_user', ['user_id' => $pedro->id]);
    }

    // ---------------------------------------------------------------- Lista de pacientes (administración)

    public function test_un_profesional_no_puede_abrir_el_listado_administrativo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente); // Aunque sea del equipo, la pantalla es de Dirección.

        Livewire::actingAs($lucia)->test(Lista::class)->assertForbidden();
    }

    public function test_direccion_ve_en_la_lista_todos_los_pacientes_aunque_no_sea_de_ningun_equipo(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $this->crearPaciente('Bruno', 'Arnal', '40111222'); // Sin equipo.
        $this->crearProfesionalDelEquipo($ana);

        $this->entrarComoDireccion();

        Livewire::test(Lista::class)
            ->assertSee('Zapata, Ana')
            ->assertSee('Arnal, Bruno')
            ->assertSee('+ Nuevo'); // El link para cargar un paciente.
    }

    // ---------------------------------------------------------------- Ficha

    public function test_un_profesional_del_equipo_ve_la_ficha_sin_el_boton_editar(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        // Se busca el link y no la palabra "Editar", para no confundirlo con otro texto de la página.
        $this->actingAs($lucia)
            ->get("/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSee('Ana Zapata')
            ->assertDontSee(route('pacientes.editar', $paciente));
    }

    public function test_un_profesional_que_no_es_del_equipo_recibe_403_en_la_ficha(): void
    {
        $paciente = $this->crearPaciente();
        $this->crearProfesionalDelEquipo($paciente);
        $pedro = User::factory()->create(); // Tiene sesión, pero no atiende a este paciente.

        $this->actingAs($pedro)
            ->get("/pacientes/{$paciente->id}")
            ->assertForbidden()
            ->assertDontSee('Zapata')
            ->assertDontSee('52.118.304');
    }

    public function test_si_sacan_a_un_profesional_del_equipo_con_la_ficha_abierta_deja_de_verla(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        // Abre la ficha siendo del equipo: la ve.
        $ficha = Livewire::actingAs($lucia)->test(Ficha::class, ['paciente' => $paciente])->assertSee('Ana Zapata');

        // La sacan del equipo mientras tiene la ficha abierta.
        $paciente->profesionales()->detach($lucia->id);

        // Al refrescar, el permiso se vuelve a comprobar: ya no puede verla.
        $ficha->call('$refresh')->assertForbidden();
    }

    public function test_direccion_ve_la_ficha_aunque_no_sea_del_equipo(): void
    {
        $paciente = $this->crearPaciente();

        $this->entrarComoDireccion();

        // Ve la ficha, pero el link para editar ya no está ahí: se edita desde Administración › Pacientes.
        $this->get("/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSee('Ana Zapata')
            ->assertDontSee(route('pacientes.editar', $paciente));
    }

    // ---------------------------------------------------------------- Historial y adjuntos: solo el equipo

    public function test_direccion_fuera_del_equipo_ve_la_ficha_pero_no_el_historial(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->crearMensaje($paciente, $lucia, 'Texto reservado al equipo.');

        $this->entrarComoDireccion();

        // Ve los datos del paciente, pero en lugar del historial le aparece el aviso.
        $this->get("/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSee('52.118.304')
            ->assertSee(self::AVISO)
            ->assertDontSee('Texto reservado al equipo.');
    }

    public function test_direccion_fuera_del_equipo_no_puede_enviar_mensajes(): void
    {
        $paciente = $this->crearPaciente();

        $this->entrarComoDireccion();

        // Aunque la vista no le muestre el formulario, el método se podría llamar igual: tiene que rechazarlo.
        Livewire::test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'No debería guardarse.')
            ->call('enviar')
            ->assertForbidden();

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_direccion_fuera_del_equipo_no_puede_descargar_un_adjunto(): void
    {
        // Disco de mentira, para no mezclar el adjunto del test con los archivos reales.
        Storage::fake('local');

        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $mensaje = $this->crearMensaje($paciente, $lucia, 'Adjunto el informe.');
        $mensaje->adjuntar(UploadedFile::fake()->createWithContent('informe.pdf', 'Contenido confidencial'));
        $adjunto = $mensaje->getFirstMedia('adjuntos');

        $this->entrarComoDireccion();

        $this->get("/adjuntos/{$adjunto->id}")->assertForbidden();
    }

    public function test_direccion_dentro_del_equipo_ve_el_historial_y_puede_escribir(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->crearMensaje($paciente, $lucia, 'Texto reservado al equipo.');

        $direccion = $this->entrarComoDireccion();
        $paciente->profesionales()->attach($direccion->id); // Además de ser de Dirección, lo atiende.

        Livewire::test(Mensajes::class, ['paciente' => $paciente])
            ->assertSee('Texto reservado al equipo.')
            ->assertDontSee(self::AVISO)
            ->set('cuerpo', 'Respuesta de Dirección.')
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mensajes', ['paciente_id' => $paciente->id, 'user_id' => $direccion->id]);
    }

    // ---------------------------------------------------------------- Menú

    public function test_el_menu_muestra_administracion_solo_a_direccion(): void
    {
        // Se pide el inicio, donde esa palabra solo puede venir del menú.
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            // El link del menú al Inicio ahora dice "Mis pacientes".
            ->assertSeeInOrder(['href="'.route('inicio').'"', 'Mis pacientes'], false)
            ->assertDontSee('Administración')
            ->assertDontSee('Profesionales y disciplinas')
            // Tampoco quedan los links escondidos en el HTML.
            ->assertDontSee(route('pacientes'), false)
            ->assertDontSee(route('usuarios'), false)
            // "Mi perfil" y "Salir" están en el desplegable del nombre: siguen en la página para todos.
            ->assertSee('Mi perfil')
            ->assertSee('Salir');

        $this->entrarComoDireccion();

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['href="'.route('inicio').'"', 'Mis pacientes'], false)
            // "Administración" ya no es un link: es un botón que abre un desplegable con sus dos pantallas.
            ->assertSeeInOrder([
                'Administración',
                'href="'.route('pacientes').'"', 'Pacientes',
                'href="'.route('usuarios').'"', 'Profesionales y disciplinas',
            ], false)
            ->assertSee('Mi perfil')
            ->assertSee('Salir');
    }

    public function test_el_menu_mobile_muestra_administracion_solo_a_direccion(): void
    {
        // El menú mobile es el panel que abre el botón ☰ (aria-label="Menú"): lo que venga después de ese botón es suyo.
        $boton = 'aria-label="Menú"';

        // Un profesional tiene el botón ☰, pero su panel no trae las pantallas de administración.
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSeeInOrder([$boton, 'Mis pacientes', 'Mi perfil', 'Salir'], false)
            ->assertDontSee('Profesionales y disciplinas');

        $this->entrarComoDireccion();

        $html = $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                $boton,
                'Administración',
                'href="'.route('pacientes').'"', 'Pacientes',
                'href="'.route('usuarios').'"', 'Profesionales y disciplinas',
            ], false)
            ->getContent();

        // Dos veces: una en el desplegable de desktop y otra en el panel mobile.
        $this->assertSame(2, substr_count($html, 'Profesionales y disciplinas'));
    }

    public function test_el_desplegable_de_administracion_esta_en_todas_las_pantallas_para_direccion(): void
    {
        $paciente = $this->crearPaciente();
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $direccion = $this->entrarComoDireccion();

        // Las de administración y también las de afuera (inicio, perfil y ficha): ya no hay submenú propio de la zona.
        $urls = array_merge(
            $this->urlsDeAdministracion($paciente, $direccion, $disciplina),
            ['/', '/mi-perfil', "/pacientes/{$paciente->id}"],
        );

        foreach ($urls as $url) {
            // Los links están siempre en el HTML: el desplegable solo los oculta hasta que se hace clic.
            // "Profesionales y disciplinas" no está escrito así en ningún otro lado de la app.
            $this->get($url)
                ->assertOk()
                ->assertSeeInOrder([
                    'Administración',
                    'href="'.route('pacientes').'"',
                    'href="'.route('usuarios').'"', 'Profesionales y disciplinas',
                ], false);
        }
    }

    public function test_el_desplegable_de_administracion_no_esta_en_ninguna_pantalla_para_un_profesional(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->actingAs($lucia);

        // Las tres pantallas a las que puede entrar un profesional.
        foreach (['/', '/mi-perfil', "/pacientes/{$paciente->id}"] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('Administración')
                ->assertDontSee('Profesionales y disciplinas')
                ->assertDontSee(route('usuarios'), false)
                // Tampoco el link al listado de pacientes. Vale también para la ficha: su link de arriba
                // ya no va a ese listado (que a un profesional le da 403) sino al Inicio.
                ->assertDontSee(route('pacientes'), false);
        }
    }
}
