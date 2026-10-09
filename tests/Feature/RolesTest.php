<?php

namespace Tests\Feature;

use App\Livewire\Disciplinas;
use App\Livewire\Pacientes\Ficha;
use App\Livewire\Pacientes\Formulario;
use App\Livewire\Pacientes\Lista;
use App\Livewire\Pacientes\Mensajes;
use App\Livewire\Usuarios;
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
 * Dirección carga y edita pacientes, usuarios y disciplinas, y ve la ficha de todos los pacientes.
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

    public function test_un_profesional_recibe_403_en_las_pantallas_de_gestion(): void
    {
        $paciente = $this->crearPaciente();
        // Es del equipo de ese paciente: igual no puede editarlo.
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $this->actingAs($lucia);

        $this->get('/disciplinas')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();
        $this->get('/pacientes/crear')->assertForbidden();
        $this->get("/pacientes/{$paciente->id}/editar")->assertForbidden();
    }

    public function test_direccion_entra_a_las_pantallas_de_gestion(): void
    {
        $paciente = $this->crearPaciente();

        $this->entrarComoDireccion();

        $this->get('/disciplinas')->assertOk();
        $this->get('/usuarios')->assertOk();
        $this->get('/pacientes/crear')->assertOk();
        $this->get("/pacientes/{$paciente->id}/editar")->assertOk();
    }

    /*
     * Los tres tests que siguen prueban las ACCIONES, no la pantalla: quien ya la tenía abierta
     * (o arma el pedido a mano) no tiene que poder guardar sin ser de Dirección.
     * Por eso la pantalla se abre como Dirección y recién después se cambia la sesión a un profesional.
     */

    public function test_un_profesional_no_puede_guardar_un_usuario(): void
    {
        $pantalla = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(Usuarios::class)
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('rol', 'direccion'); // El caso más grave: fabricarse un usuario de Dirección.

        $this->actingAs(User::factory()->create());

        $pantalla->call('guardar')->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'ana@andar.test']);
    }

    public function test_un_profesional_no_puede_cambiarse_el_rol_a_direccion(): void
    {
        $lucia = User::factory()->create();

        $pantalla = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(Usuarios::class)
            ->call('editar', $lucia->id)
            ->set('rol', 'direccion');

        $this->actingAs($lucia);

        $pantalla->call('guardar')->assertForbidden();

        $this->assertSame('profesional', $lucia->fresh()->rol);
    }

    public function test_un_profesional_no_puede_guardar_una_disciplina(): void
    {
        $pantalla = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(Disciplinas::class)
            ->set('nombre', 'Psicología');

        $this->actingAs(User::factory()->create());

        $pantalla->call('guardar')->assertForbidden();

        $this->assertDatabaseCount('disciplinas', 0);
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

    // ---------------------------------------------------------------- Lista de pacientes

    public function test_un_profesional_ve_en_la_lista_solo_los_pacientes_de_su_equipo(): void
    {
        $ana = $this->crearPaciente('Ana', 'Zapata', '52118304');
        $bruno = $this->crearPaciente('Bruno', 'Arnal', '40111222');
        $this->crearPaciente('Carla', 'Molina', '45333444'); // Sin equipo: tampoco es de Lucía.
        $lucia = $this->crearProfesionalDelEquipo($ana, 'Lucía Pérez');
        $this->crearProfesionalDelEquipo($bruno, 'Marta Díaz'); // A Bruno lo atiende otra persona.

        Livewire::actingAs($lucia)
            ->test(Lista::class)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno')
            ->assertDontSee('40.111.222') // Ni el nombre ni el DNI de un paciente ajeno.
            ->assertDontSee('Molina, Carla')
            // Solo Dirección carga pacientes: no se le ofrece el botón.
            ->assertDontSee('Nuevo paciente');
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
            ->assertSee('Nuevo paciente');
    }

    public function test_a_un_profesional_sin_pacientes_la_lista_le_avisa_que_no_tiene_asignados(): void
    {
        $this->crearPaciente(); // Hay un paciente cargado, pero no es suyo.

        // El aviso es distinto del de Dirección ("Todavía no hay pacientes cargados."), que acá sería falso.
        Livewire::actingAs(User::factory()->create())
            ->test(Lista::class)
            ->assertSee('Todavía no tenés pacientes asignados.')
            ->assertDontSee('Todavía no hay pacientes cargados.')
            ->assertDontSee('Zapata, Ana');
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

    public function test_direccion_ve_la_ficha_con_el_boton_editar_aunque_no_sea_del_equipo(): void
    {
        $paciente = $this->crearPaciente();

        $this->entrarComoDireccion();

        $this->get("/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSee('Ana Zapata')
            ->assertSee(route('pacientes.editar', $paciente));
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

    public function test_el_menu_muestra_disciplinas_y_usuarios_solo_a_direccion(): void
    {
        // Se pide el inicio, donde esas dos palabras solo pueden venir del menú.
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Pacientes')
            ->assertDontSee('Disciplinas')
            ->assertDontSee('Usuarios');

        $this->entrarComoDireccion();

        $this->get('/')
            ->assertOk()
            ->assertSee('Pacientes')
            ->assertSee('Disciplinas')
            ->assertSee('Usuarios');
    }
}
