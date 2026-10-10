<?php

namespace Tests\Feature;

use App\Livewire\Usuarios\Formulario;
use App\Livewire\Usuarios\Lista;
use App\Models\Disciplina;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de los usuarios (los profesionales): el listado "Profesionales y disciplinas"
 * con su buscador, filtros y orden, y el formulario para crear y editar, que es una página aparte.
 * (El panel de disciplinas de ese mismo listado se prueba en DisciplinasTest.)
 */
class UsuariosTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /**
     * Abre el formulario con la sesión de alguien de Dirección, que es el único rol que puede usarlo.
     * Sin $usuario es el de alta; con $usuario, el de edición de esa persona.
     * (Que un profesional no pueda entrar se prueba en RolesTest.)
     *
     * Ojo al contar usuarios: quien tiene la sesión también es una fila de la tabla users.
     */
    private function formulario(?User $usuario = null): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Formulario::class, $usuario ? ['usuario' => $usuario] : []);
    }

    /**
     * Abre el listado con la sesión de alguien de Dirección.
     * $datos fija el nombre o el email de esa persona, que también sale en la tabla.
     */
    private function lista(array $datos = []): Testable
    {
        $this->entrarComoDireccion($datos);

        return Livewire::test(Lista::class);
    }

    /**
     * Carga tres personas para probar el orden, el buscador y el filtro, y abre el listado con la sesión de Marta (Dirección).
     * La cantidad de pacientes va a propósito en otro orden que los nombres: así se distingue por cuál columna se ordenó.
     *
     *   Ana Gómez    zeta@andar.test    Profesional   atiende 2 pacientes
     *   Marta Díaz   marta@andar.test   Dirección     no atiende a nadie
     *   Zulema Ruiz  alfa@andar.test    Profesional   atiende 1 paciente
     */
    private function listaConTresPersonas(): Testable
    {
        $this->asignarPacientes(User::factory()->create(['name' => 'Zulema Ruiz', 'email' => 'alfa@andar.test']), 1);
        $this->asignarPacientes(User::factory()->create(['name' => 'Ana Gómez', 'email' => 'zeta@andar.test']), 2);

        return $this->lista(['name' => 'Marta Díaz', 'email' => 'marta@andar.test']);
    }

    /**
     * Crea esa cantidad de pacientes y los deja al cuidado de ese usuario (no hay factory de pacientes).
     * El DNI se arma solo, porque cada paciente necesita uno distinto. $datos pisa o agrega campos.
     */
    private function asignarPacientes(User $usuario, int $cantidad, array $datos = []): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            Paciente::create(array_merge([
                'nombre' => 'Paciente',
                'apellido' => 'De Prueba',
                'dni' => (string) (40000000 + Paciente::count()),
                'fecha_nacimiento' => '2018-10-08',
            ], $datos))->profesionales()->attach($usuario->id);
        }
    }

    // ---------------------------------------------------------------- Rutas

    public function test_sin_sesion_las_pantallas_de_usuarios_redirigen_al_login(): void
    {
        $usuario = User::factory()->create();

        $this->get('/administracion/profesionales')->assertRedirect('/login');
        $this->get('/administracion/profesionales/crear')->assertRedirect('/login');
        $this->get("/administracion/profesionales/{$usuario->id}/editar")->assertRedirect('/login');
    }

    public function test_direccion_ve_el_listado_de_profesionales(): void
    {
        $this->entrarComoDireccion();

        $this->get('/administracion/profesionales')
            ->assertOk()
            ->assertSeeLivewire(Lista::class);
    }

    public function test_direccion_ve_la_pantalla_de_crear_profesional(): void
    {
        $this->entrarComoDireccion();

        // "crear" no se tiene que confundir con el id de un usuario.
        $this->get('/administracion/profesionales/crear')
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            ->assertSee('Nuevo profesional');
    }

    public function test_direccion_ve_la_pantalla_de_editar_profesional(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Gómez']);
        $this->entrarComoDireccion();

        $this->get("/administracion/profesionales/{$usuario->id}/editar")
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            ->assertSee('Editar profesional');
    }

    public function test_editar_un_profesional_que_no_existe_da_404(): void
    {
        $this->entrarComoDireccion();

        $this->get('/administracion/profesionales/9999/editar')->assertNotFound();
    }

    public function test_las_direcciones_viejas_de_usuarios_ya_no_existen(): void
    {
        $this->entrarComoDireccion();

        // La pantalla se mudó a /administracion/profesionales.
        $this->get('/usuarios')->assertNotFound();
    }

    // ---------------------------------------------------------------- Formulario: crear

    public function test_se_puede_crear_un_usuario_con_una_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        Disciplina::create(['nombre' => 'Fonoaudiología']); // No se tilda: no tiene que quedar vinculada.

        $this->formulario()
            // El formulario de alta arranca vacío.
            ->assertSet('usuarioId', null)
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('telefono', '')
            ->assertSet('admin', false)
            ->assertSet('disciplinas', [])
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            // Las casillas mandan los ids como texto, por eso se pasan como string.
            ->set('disciplinas', [(string) $disciplina->id])
            ->call('guardar')
            ->assertHasNoErrors()
            // Después de guardar vuelve al listado.
            ->assertRedirect(route('usuarios'));

        $this->assertDatabaseHas('users', ['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);

        $usuario = User::where('email', 'ana@andar.test')->firstOrFail();

        // Las disciplinas no son una columna del usuario: quedan en la tabla intermedia disciplina_user.
        $this->assertDatabaseCount('disciplina_user', 1);
        $this->assertDatabaseHas('disciplina_user', ['user_id' => $usuario->id, 'disciplina_id' => $disciplina->id]);
    }

    public function test_se_puede_crear_un_usuario_con_varias_disciplinas(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);

        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('disciplinas', [(string) $psicologia->id, (string) $fonoaudiologia->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'ana@andar.test')->firstOrFail();

        $this->assertDatabaseCount('disciplina_user', 2);
        $this->assertDatabaseHas('disciplina_user', ['user_id' => $usuario->id, 'disciplina_id' => $psicologia->id]);
        $this->assertDatabaseHas('disciplina_user', ['user_id' => $usuario->id, 'disciplina_id' => $fonoaudiologia->id]);
    }

    public function test_se_puede_crear_un_usuario_sin_disciplina(): void
    {
        Disciplina::create(['nombre' => 'Psicología']); // Existe, pero no se tilda.

        // No se toca "disciplinas": es lo mismo que dejar todas las casillas sin tildar.
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertCount(0, User::where('email', 'ana@andar.test')->firstOrFail()->disciplinas);
        $this->assertDatabaseCount('disciplina_user', 0);
    }

    public function test_el_usuario_nuevo_puede_entrar_con_la_contrasena_inicial(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'ana@andar.test')->firstOrFail();

        $this->assertSame('password', Formulario::CONTRASENA_INICIAL);
        // En la base queda encriptada, nunca el texto tal cual.
        $this->assertNotSame('password', $usuario->password);
        $this->assertTrue(Hash::check('password', $usuario->password));
        $this->assertTrue(Auth::attempt(['email' => 'ana@andar.test', 'password' => 'password']));
    }

    public function test_al_crear_se_quitan_los_espacios_de_mas_de_nombre_y_email(): void
    {
        $this->formulario()
            ->set('name', '  Ana Gómez  ')
            ->set('email', '  ana@andar.test  ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);
    }

    public function test_un_email_duplicado_da_error(): void
    {
        User::factory()->create(['email' => 'ana@andar.test']);

        $this->formulario()
            ->set('name', 'Otra Ana')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique'])
            ->assertNoRedirect(); // Con errores se queda en el formulario.

        // No se creó otro: siguen siendo Ana y quien tiene la sesión.
        $this->assertDatabaseCount('users', 2);
    }

    public function test_un_email_invalido_da_error(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'esto-no-es-un-email')
            ->call('guardar')
            ->assertHasErrors(['email' => 'email']);

        $this->assertDatabaseCount('users', 1); // Solo quien tiene la sesión: no se creó nadie.
    }

    public function test_el_email_vacio_da_error(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', '')
            ->call('guardar')
            ->assertHasErrors(['email' => 'required']);

        $this->assertDatabaseCount('users', 1); // Solo quien tiene la sesión: no se creó nadie.
    }

    public function test_el_nombre_vacio_da_error(): void
    {
        $this->formulario()
            ->set('name', '')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseCount('users', 1); // Solo quien tiene la sesión: no se creó nadie.
    }

    public function test_una_disciplina_inexistente_da_error(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('disciplinas', ['999']) // No hay ninguna disciplina con ese id.
            ->call('guardar')
            // El error queda anotado en la casilla que falló (la primera de la lista: posición 0).
            ->assertHasErrors(['disciplinas.0' => 'exists'])
            ->assertSee('Una de las disciplinas elegidas no existe.');

        $this->assertDatabaseCount('users', 1); // Solo quien tiene la sesión: no se creó nadie.
        $this->assertDatabaseCount('disciplina_user', 0);
    }

    // ---------------------------------------------------------------- Formulario: editar

    public function test_editar_carga_los_datos_del_usuario_con_sus_disciplinas_tildadas(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        Disciplina::create(['nombre' => 'Psicomotricidad']); // Existe, pero el usuario no la tiene.
        $usuario = User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);
        $usuario->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);

        $this->formulario($usuario)
            ->assertSet('usuarioId', $usuario->id)
            ->assertSet('name', 'Ana Gómez')
            ->assertSet('email', 'ana@andar.test')
            // Los ids van como texto, para que coincidan con las casillas.
            // Salen por orden alfabético del nombre: primero Fonoaudiología, después Psicología.
            ->assertSet('disciplinas', [(string) $fonoaudiologia->id, (string) $psicologia->id]);
    }

    public function test_editar_un_usuario_sin_disciplina_deja_las_casillas_sin_tildar(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);
        $usuario = User::factory()->create();

        $this->formulario($usuario)->assertSet('disciplinas', []);
    }

    public function test_guardar_una_edicion_actualiza_los_datos_sin_cambiar_la_contrasena(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $psicomotricidad = Disciplina::create(['nombre' => 'Psicomotricidad']);
        $usuario = User::factory()->create([
            'name' => 'Ana Gómez',
            'email' => 'ana@andar.test',
            'password' => 'clave-propia',
        ]);
        $usuario->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);
        $hashAntes = $usuario->fresh()->password;

        $this->formulario($usuario)
            ->set('name', 'Ana María Gómez')
            ->set('email', 'anamaria@andar.test')
            // Se destilda Psicología, queda Fonoaudiología y se tilda Psicomotricidad.
            ->set('disciplinas', [(string) $fonoaudiologia->id, (string) $psicomotricidad->id])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios'));

        $usuario->refresh();

        // Se actualizó el existente, no se creó otro (el segundo es quien tiene la sesión).
        $this->assertDatabaseCount('users', 2);
        $this->assertSame('Ana María Gómez', $usuario->name);
        $this->assertSame('anamaria@andar.test', $usuario->email);

        // Quedan exactamente las tildadas: se agregó la nueva y se sacó la destildada.
        $this->assertDatabaseCount('disciplina_user', 2);
        $this->assertDatabaseMissing('disciplina_user', ['user_id' => $usuario->id, 'disciplina_id' => $psicologia->id]);
        $this->assertDatabaseHas('disciplina_user', ['user_id' => $usuario->id, 'disciplina_id' => $fonoaudiologia->id]);
        $this->assertDatabaseHas('disciplina_user', ['user_id' => $usuario->id, 'disciplina_id' => $psicomotricidad->id]);

        // El hash es exactamente el mismo: ni se pisó con la contraseña inicial ni se volvió a encriptar.
        $this->assertSame($hashAntes, $usuario->password);
        $this->assertTrue(Hash::check('clave-propia', $usuario->password));
        $this->assertFalse(Hash::check(Formulario::CONTRASENA_INICIAL, $usuario->password));
    }

    public function test_se_puede_guardar_una_edicion_sin_cambiar_el_email(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);

        // La regla de único no cuenta al propio usuario que se está editando.
        $this->formulario($usuario)
            ->set('name', 'Ana María Gómez')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario->refresh();

        $this->assertDatabaseCount('users', 2); // Ana y quien tiene la sesión.
        $this->assertSame('Ana María Gómez', $usuario->name);
        $this->assertSame('ana@andar.test', $usuario->email);
    }

    public function test_al_editar_no_se_puede_usar_el_email_de_otro_usuario(): void
    {
        User::factory()->create(['email' => 'ana@andar.test']);
        $otro = User::factory()->create(['email' => 'beto@andar.test']);

        // La regla de único solo ignora al que se edita, no a los demás.
        $this->formulario($otro)
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertSame('beto@andar.test', $otro->fresh()->email);
    }

    public function test_destildar_todas_las_disciplinas_al_editar_deja_al_usuario_sin_ninguna(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $usuario = User::factory()->create();
        $usuario->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);

        $this->formulario($usuario)
            ->set('disciplinas', []) // Todas las casillas destildadas.
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('disciplina_user', 0);
        // Se le sacan los vínculos, pero las disciplinas siguen existiendo.
        $this->assertDatabaseCount('disciplinas', 2);
    }

    public function test_cancelar_vuelve_al_listado_sin_guardar_nada(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $usuario = User::factory()->create(['name' => 'Ana Gómez']);
        $usuario->disciplinas()->attach($disciplina->id);

        // "Cancelar" ya no es una acción del componente: es un link común al listado.
        // Lo que se haya escrito en el formulario se pierde al irse, porque nada se guarda hasta apretar Guardar.
        $html = $this->formulario($usuario)
            ->set('name', 'Otro nombre')
            ->set('disciplinas', [])
            ->assertSee('Cancelar')
            ->html();

        $this->assertStringContainsString('href="'.route('usuarios').'"', $html);
        $this->assertSame('Ana Gómez', $usuario->fresh()->name);
        $this->assertDatabaseCount('disciplina_user', 1);
    }

    public function test_el_formulario_tiene_el_link_para_volver_al_listado(): void
    {
        $this->formulario()
            ->assertSeeInOrder(['href="'.route('usuarios').'"', 'Volver a profesionales'], false);
    }

    public function test_el_formulario_muestra_cada_disciplina_con_su_color(): void
    {
        Disciplina::create(['nombre' => 'Psicología', 'color' => '#ff0000']);

        // Al lado de cada casilla va la etiqueta de la disciplina: la letra de su color
        // y el fondo del mismo color casi transparente (el "1a" del final).
        $this->formulario()
            ->assertSeeHtml('style="color: #ff0000; background-color: #ff00001a">Psicología</span>');
    }

    // ---------------------------------------------------------------- Formulario: administrador

    public function test_el_rol_se_elige_con_una_sola_casilla_es_administrador(): void
    {
        $html = $this->formulario()
            ->assertSeeHtml('type="checkbox" wire:model="admin"')
            ->assertSee('Es administrador')
            ->html();

        // Ya no hay un radio por rol ni un desplegable.
        $this->assertStringNotContainsString('type="radio"', $html);
        $this->assertStringNotContainsString('wire:model="rol"', $html);
        $this->assertStringNotContainsString('<select', $html);
    }

    public function test_un_usuario_nuevo_queda_como_profesional_si_no_se_tilda_administrador(): void
    {
        $this->formulario()
            ->assertSet('admin', false) // La casilla arranca sin tildar.
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasNoErrors();

        // Un solo rol: el rol no es una columna del usuario, lo guarda Spatie Permission en sus tablas.
        $this->assertSame(['profesional'], User::where('email', 'ana@andar.test')->firstOrFail()->getRoleNames()->all());
    }

    public function test_tildar_administrador_crea_un_usuario_de_direccion(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('admin', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'ana@andar.test')->firstOrFail();

        $this->assertSame(['direccion'], $usuario->getRoleNames()->all());
        $this->assertTrue($usuario->esDireccion());

        // El formulario de alta se abre siempre sin tildar: no arrastra lo del último que se cargó.
        Livewire::test(Formulario::class)->assertSet('admin', false);
    }

    public function test_editar_carga_si_es_administrador_y_guardar_lo_cambia(): void
    {
        $usuario = User::factory()->create(); // Profesional.

        $this->formulario($usuario)
            ->assertSet('admin', false)
            ->set('admin', true)
            ->call('guardar')
            ->assertHasNoErrors();

        // Queda solo con el rol nuevo: no acumula el anterior.
        $this->assertSame(['direccion'], $usuario->fresh()->getRoleNames()->all());

        // Y al volver a abrirlo, la casilla ya está tildada.
        Livewire::test(Formulario::class, ['usuario' => $usuario->fresh()])->assertSet('admin', true);
    }

    public function test_no_se_puede_destildar_administrador_a_la_unica_persona_de_direccion(): void
    {
        // Quien tiene la sesión es la única persona de Dirección. Hay un profesional, pero no cuenta.
        $direccion = $this->entrarComoDireccion();
        User::factory()->create();

        // Si se pudiera, el centro quedaría sin nadie que cargue usuarios ni pacientes.
        Livewire::test(Formulario::class, ['usuario' => $direccion])
            ->assertSet('admin', true)
            ->set('admin', false)
            ->call('guardar')
            ->assertHasErrors('admin')
            ->assertSeeHtml('Es la única persona administradora: antes hay que hacer administradora a otra.')
            ->assertNoRedirect();

        $this->assertSame(['direccion'], $direccion->fresh()->getRoleNames()->all());
    }

    public function test_la_unica_persona_de_direccion_puede_editar_sus_otros_datos(): void
    {
        $direccion = $this->entrarComoDireccion();

        // La regla solo frena el cambio de rol: mientras siga en Dirección, el resto se guarda normal.
        Livewire::test(Formulario::class, ['usuario' => $direccion])
            ->set('name', 'Nombre Nuevo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Nombre Nuevo', $direccion->fresh()->name);
        $this->assertTrue($direccion->fresh()->esDireccion());
    }

    public function test_se_puede_destildar_administrador_cuando_hay_otra_persona_de_direccion(): void
    {
        $this->entrarComoDireccion();
        $otra = User::factory()->direccion()->create();

        Livewire::test(Formulario::class, ['usuario' => $otra])
            ->set('admin', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(['profesional'], $otra->fresh()->getRoleNames()->all());
    }

    // ---------------------------------------------------------------- Formulario: teléfono

    public function test_al_crear_se_guarda_el_telefono_sin_espacios_de_mas(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('telefono', '  11 2233-4455 ')
            ->call('guardar')
            ->assertHasNoErrors();

        // Se guarda tal cual se escribió (sin cifrar: es contacto laboral), solo sin los espacios de las puntas.
        $this->assertDatabaseHas('users', ['email' => 'ana@andar.test', 'telefono' => '11 2233-4455']);
    }

    public function test_el_telefono_es_opcional_y_vacio_se_guarda_como_null(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('telefono', '   ') // Solo espacios cuenta como vacío.
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull(User::where('email', 'ana@andar.test')->firstOrFail()->telefono);
    }

    public function test_editar_carga_el_telefono_y_guardar_lo_cambia(): void
    {
        $usuario = User::factory()->create(['telefono' => '1122334455']);

        $this->formulario($usuario)
            ->assertSet('telefono', '1122334455')
            ->set('telefono', '1199887766')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('1199887766', $usuario->fresh()->telefono);
    }

    public function test_vaciar_el_telefono_al_editar_lo_deja_en_null(): void
    {
        $usuario = User::factory()->create(['telefono' => '1122334455']);

        $this->formulario($usuario)
            ->set('telefono', '')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull($usuario->fresh()->telefono);
    }

    public function test_un_usuario_sin_telefono_abre_el_formulario_con_el_campo_vacio(): void
    {
        $usuario = User::factory()->create(['telefono' => null]);

        $this->formulario($usuario)->assertSet('telefono', '');
    }

    public function test_el_telefono_admite_hasta_50_caracteres(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('telefono', str_repeat('1', 51))
            ->call('guardar')
            ->assertHasErrors(['telefono' => 'max'])
            ->assertSeeHtml('El texto es demasiado largo.')
            // Con 50 justos pasa.
            ->set('telefono', str_repeat('1', 50))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(str_repeat('1', 50), User::where('email', 'ana@andar.test')->firstOrFail()->telefono);
    }

    // ---------------------------------------------------------------- Formulario: contraseña

    public function test_al_crear_la_contrasena_viene_precargada_con_la_inicial_y_a_la_vista(): void
    {
        $this->formulario()
            ->assertSet('password', Formulario::CONTRASENA_INICIAL)
            // A la vista (no con puntitos): la escribe Dirección para pasársela a la persona.
            ->assertSeeHtml('type="text" wire:model="password"')
            ->assertDontSeeHtml('type="password"');
    }

    public function test_al_crear_se_puede_cambiar_la_contrasena_precargada_y_el_usuario_entra_con_esa(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('password', 'clave-elegida')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'ana@andar.test')->firstOrFail();

        // En la base queda encriptada, no el texto.
        $this->assertNotSame('clave-elegida', $usuario->password);
        $this->assertTrue(Hash::check('clave-elegida', $usuario->password));
        $this->assertFalse(Hash::check(Formulario::CONTRASENA_INICIAL, $usuario->password));
    }

    public function test_al_crear_la_contrasena_es_obligatoria(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('password', '') // Se borró la precargada.
            ->call('guardar')
            ->assertHasErrors(['password' => 'required']);

        $this->assertDatabaseMissing('users', ['email' => 'ana@andar.test']);
    }

    public function test_al_crear_una_contrasena_de_menos_de_8_caracteres_da_error(): void
    {
        $this->formulario()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('password', '1234567')
            ->call('guardar')
            ->assertHasErrors(['password' => 'min'])
            ->assertSeeHtml('La contraseña tiene que tener al menos 8 caracteres.');

        $this->assertDatabaseMissing('users', ['email' => 'ana@andar.test']);
    }

    public function test_al_editar_la_contrasena_arranca_vacia(): void
    {
        $usuario = User::factory()->create(['password' => 'clave-propia']);

        // Ni la inicial ni (mucho menos) la que tiene guardada: vacío = no tocarla.
        $html = $this->formulario($usuario)
            ->assertSet('password', '')
            ->assertSee('Dejar vacío para no cambiarla')
            ->html();

        $this->assertStringNotContainsString($usuario->fresh()->password, $html);
    }

    public function test_al_editar_escribir_una_contrasena_reemplaza_a_la_que_tenia(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@andar.test', 'password' => 'clave-propia']);

        $this->formulario($usuario)
            ->set('password', 'clave-nueva-1')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios'));

        $usuario->refresh();

        $this->assertTrue(Hash::check('clave-nueva-1', $usuario->password));
        $this->assertFalse(Hash::check('clave-propia', $usuario->password));
    }

    public function test_al_editar_una_contrasena_corta_da_error_y_no_cambia_la_que_tenia(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Gómez', 'password' => 'clave-propia']);

        // Tampoco se guarda el nombre nuevo: si un campo falla, no se guarda ninguno.
        $this->formulario($usuario)
            ->set('name', 'Otro nombre')
            ->set('password', 'corta')
            ->call('guardar')
            ->assertHasErrors(['password' => 'min']);

        $this->assertTrue(Hash::check('clave-propia', $usuario->fresh()->password));
        $this->assertSame('Ana Gómez', $usuario->fresh()->name);
    }

    // ---------------------------------------------------------------- Listado

    public function test_la_lista_muestra_los_usuarios_con_su_email_y_una_etiqueta_por_disciplina(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología', 'color' => '#ff0000']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología', 'color' => '#00aa55']);
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz', 'email' => 'zulema@andar.test']);
        $zulema->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);
        User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']); // Sin disciplina.

        $html = $this->lista()
            // La lista sale ordenada por nombre, con el email debajo de cada uno.
            // Quien no tiene disciplina lleva una raya; quien tiene varias, una etiqueta por cada una, en orden alfabético.
            ->assertSeeInOrder([
                'Ana Gómez', 'ana@andar.test', '—',
                'Zulema Ruiz', 'zulema@andar.test', 'Fonoaudiología', 'Psicología',
            ])
            // Cada etiqueta va pintada con el color de su disciplina: la letra del color
            // y el fondo del mismo color casi transparente (el "1a" del final).
            ->assertSeeHtml('style="color: #00aa55; background-color: #00aa551a">Fonoaudiología</span>')
            ->assertSeeHtml('style="color: #ff0000; background-color: #ff00001a">Psicología</span>')
            ->html();

        // Una sola etiqueta de cada una: la de Zulema. (Ana no tiene, y el panel de disciplinas usa un puntito, no etiquetas.)
        $this->assertSame(2, substr_count($html, 'style="color:'));
    }

    public function test_quien_es_de_direccion_lleva_la_etiqueta_admin_y_ya_no_hay_columna_de_rol(): void
    {
        User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);

        $html = $this->lista(['name' => 'Zulema Ruiz', 'email' => 'zulema@andar.test'])
            // La etiqueta va al lado del nombre, antes del email (que está debajo).
            ->assertSeeInOrder(['Ana Gómez', 'ana@andar.test', 'Zulema Ruiz', 'Admin', 'zulema@andar.test'])
            ->html();

        // Una sola: Ana es profesional y no lleva ninguna etiqueta de rol.
        $this->assertSame(1, substr_count($html, 'Admin'));
        // El rol ya no se escribe en la tabla ni hay un filtro para elegirlo.
        $this->assertStringNotContainsString('Dirección', $html);
        $this->assertStringNotContainsString('wire:model.live="rol"', $html);
        // "Profesional" con mayúscula solo está en el título ("Profesionales") y ahí no va suelta.
        $this->assertDoesNotMatchRegularExpression('/Profesional\b/u', $html);
    }

    public function test_la_lista_muestra_cuantos_pacientes_atiende_cada_uno(): void
    {
        $lista = $this->listaConTresPersonas();
        // Los dados de baja también cuentan: sigue siendo su paciente.
        $this->asignarPacientes(User::where('name', 'Zulema Ruiz')->firstOrFail(), 1, ['activo' => false]);
        $lista->call('$refresh');

        // Se lee el dato que recibe la vista: un número suelto en el HTML se confundiría con cualquier otro.
        $cantidades = $lista->viewData('usuarios')->pluck('pacientes_count', 'name')->all();

        $this->assertSame(['Ana Gómez' => 2, 'Marta Díaz' => 0, 'Zulema Ruiz' => 2], $cantidades);

        // Y en la pantalla cada número va en la última celda del renglón de esa persona.
        $lista->assertSeeHtmlInOrder(['Ana Gómez', '>2</td>', 'Marta Díaz', '>0</td>', 'Zulema Ruiz', '>2</td>']);
    }

    public function test_al_lado_del_titulo_dice_cuantos_profesionales_hay_en_la_tabla(): void
    {
        $lista = $this->listaConTresPersonas();

        // La etiqueta lleva solo el número (ya no dice "3 profesionales"), al lado del título.
        // Cuenta a todos los usuarios, también a quien es de Dirección.
        $lista->assertSeeHtmlInOrder(['Profesionales', 'text-brand">3</span>']);
        // Cuenta lo que se ve: cambia con el buscador.
        $lista->set('buscar', 'gomez')->assertSeeHtmlInOrder(['Profesionales', 'text-brand">1</span>']);
        // La segunda etiqueta con número es la del panel de disciplinas (acá no hay ninguna): esa no depende del buscador.
        $this->assertSame(2, substr_count($lista->set('buscar', 'nadie-se-llama-asi')->html(), 'text-brand">0</span>'));
    }

    public function test_el_nombre_en_la_lista_lleva_a_editar_y_hay_un_link_para_crear(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Gómez']);

        $this->lista()
            // El link "+ Nuevo" va dentro de la tarjeta, al lado del título.
            ->assertSeeInOrder(['Profesionales', 'href="'.route('usuarios.crear').'"', '+ Nuevo'], false)
            ->assertSeeInOrder(['href="'.route('usuarios.editar', $usuario).'"', 'Ana Gómez'], false);
    }

    public function test_la_columna_pacientes_va_alineada_a_la_derecha(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Gómez']);
        $this->asignarPacientes($usuario, 3);

        // El número de cada fila va en una celda con "text-right".
        $this->lista()->assertSeeHtml('<td class="px-4 py-3 text-right text-muted">3</td>');
    }

    // ---------------------------------------------------------------- Listado: buscador

    public function test_el_buscador_encuentra_por_nombre_sin_importar_tildes_ni_mayusculas(): void
    {
        $lista = $this->listaConTresPersonas();

        // "gomez" sin tilde y en minúscula encuentra a "Gómez".
        $lista->set('buscar', 'gomez')
            ->assertSee('Ana Gómez')
            ->assertDontSee('Zulema Ruiz')
            ->assertDontSee('Marta Díaz');

        // Y al revés: escrito con tilde y en mayúsculas encuentra a "Díaz".
        $lista->set('buscar', 'DÍAZ')
            ->assertSee('Marta Díaz')
            ->assertDontSee('Ana Gómez');

        // Con el buscador vacío vuelven todos.
        $lista->set('buscar', '')
            ->assertSee('Ana Gómez')
            ->assertSee('Zulema Ruiz')
            ->assertSee('Marta Díaz');
    }

    public function test_el_buscador_encuentra_por_email(): void
    {
        $this->listaConTresPersonas()
            ->set('buscar', 'ALFA@andar')
            ->assertSee('Zulema Ruiz')
            ->assertDontSee('Ana Gómez')
            ->assertDontSee('Marta Díaz');
    }

    public function test_si_la_busqueda_no_encuentra_a_nadie_la_tabla_lo_avisa(): void
    {
        $this->listaConTresPersonas()
            ->assertDontSee('No hay profesionales para mostrar.')
            ->set('buscar', 'nadie-se-llama-asi')
            ->assertSee('No hay profesionales para mostrar.')
            ->assertDontSee('Ana Gómez');
    }

    // ---------------------------------------------------------------- Listado: filtros

    public function test_el_filtro_por_disciplina_muestra_solo_a_quienes_la_tienen(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $lista = $this->listaConTresPersonas();
        // Ana tiene las dos, Zulema solo Fonoaudiología y Marta ninguna.
        User::where('name', 'Ana Gómez')->firstOrFail()->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);
        User::where('name', 'Zulema Ruiz')->firstOrFail()->disciplinas()->attach($fonoaudiologia->id);

        // El desplegable manda el id como texto; vacío es "Todas las disciplinas".
        $lista->assertSet('disciplina', '') // Abre sin filtrar.
            ->set('disciplina', (string) $psicologia->id)
            ->assertSee('Ana Gómez')
            ->assertDontSee('Zulema Ruiz')
            ->assertDontSee('Marta Díaz');

        $lista->set('disciplina', (string) $fonoaudiologia->id)
            ->assertSee('Ana Gómez')
            ->assertSee('Zulema Ruiz')
            ->assertDontSee('Marta Díaz');

        // Sin filtro vuelven todos, también Marta, que no tiene ninguna.
        $lista->set('disciplina', '')
            ->assertSee('Ana Gómez')
            ->assertSee('Zulema Ruiz')
            ->assertSee('Marta Díaz');
    }

    public function test_el_desplegable_del_filtro_ofrece_todas_las_disciplinas(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        Disciplina::create(['nombre' => 'Fonoaudiología']); // Nadie la tiene: igual se puede elegir.

        $this->lista()
            ->assertSeeHtml('wire:model.live="disciplina"')
            // Primero la opción de no filtrar y después cada disciplina, por nombre.
            ->assertSeeInOrder(['Todas las disciplinas', 'Fonoaudiología', 'Psicología'])
            ->assertSeeHtml('<option value="'.$psicologia->id.'">Psicología</option>');
    }

    public function test_el_filtro_por_una_disciplina_que_nadie_tiene_deja_la_tabla_vacia(): void
    {
        $psicomotricidad = Disciplina::create(['nombre' => 'Psicomotricidad']);

        $this->listaConTresPersonas()
            ->set('disciplina', (string) $psicomotricidad->id)
            ->assertSee('No hay profesionales para mostrar.')
            ->assertDontSee('Ana Gómez');
    }

    public function test_el_buscador_y_el_filtro_se_combinan(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $lista = $this->listaConTresPersonas();
        User::where('name', 'Ana Gómez')->firstOrFail()->disciplinas()->attach($psicologia->id);
        User::where('name', 'Zulema Ruiz')->firstOrFail()->disciplinas()->attach($psicologia->id);

        // "andar" está en el email de los tres; el filtro deja solo a las dos que tienen Psicología.
        $lista->set('buscar', 'andar')
            ->set('disciplina', (string) $psicologia->id)
            ->assertSee('Ana Gómez')
            ->assertSee('Zulema Ruiz')
            ->assertDontSee('Marta Díaz');

        // Y si además se busca a una sola de ellas, queda esa.
        $lista->set('buscar', 'zulema')
            ->assertSee('Zulema Ruiz')
            ->assertDontSee('Ana Gómez');
    }

    // ---------------------------------------------------------------- Listado: orden
    //
    // Después de una acción (call o set) el orden se comprueba con assertSeeHtmlInOrder y no con assertSeeInOrder:
    // este último mira la respuesta cruda de Livewire, que tras una acción viene como JSON con las tildes
    // escritas en código ("G\u00f3mez"), y entonces no encuentra los nombres con tilde.

    public function test_la_lista_abre_ordenada_por_nombre_y_un_clic_en_la_columna_invierte_el_sentido(): void
    {
        // "Álvaro" lleva tilde: tiene que ordenarse en la A, no después de la Z.
        User::factory()->create(['name' => 'Álvaro Paz', 'email' => 'paz@andar.test']);

        $this->listaConTresPersonas()
            ->assertSet('orden', 'nombre')
            ->assertSet('ascendente', true)
            ->assertSeeInOrder(['Álvaro Paz', 'Ana Gómez', 'Marta Díaz', 'Zulema Ruiz'])
            ->call('ordenarPor', 'nombre')
            ->assertSet('orden', 'nombre')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Zulema Ruiz', 'Marta Díaz', 'Ana Gómez', 'Álvaro Paz']);
    }

    public function test_la_lista_se_puede_ordenar_por_cantidad_de_pacientes(): void
    {
        $this->listaConTresPersonas()
            ->call('ordenarPor', 'pacientes')
            ->assertSet('orden', 'pacientes')
            // Una columna nueva arranca de menor a mayor: Marta (0), Zulema (1) y Ana (2). No es el orden de los nombres.
            ->assertSet('ascendente', true)
            ->assertSeeHtmlInOrder(['Marta Díaz', 'Zulema Ruiz', 'Ana Gómez'])
            ->call('ordenarPor', 'pacientes')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Ana Gómez', 'Zulema Ruiz', 'Marta Díaz']);
    }

    public function test_quienes_atienden_la_misma_cantidad_de_pacientes_quedan_por_nombre(): void
    {
        // Ninguno atiende a nadie: empatan todos. "Álvaro" lleva tilde y tiene que desempatar en la A.
        User::factory()->create(['name' => 'Zulema Ruiz']);
        User::factory()->create(['name' => 'Álvaro Paz']);
        User::factory()->create(['name' => 'Ana Gómez']);
        // Bruno es el único con un paciente.
        $this->asignarPacientes(User::factory()->create(['name' => 'Bruno Sosa']), 1);

        $this->lista(['name' => 'Marta Díaz'])
            ->call('ordenarPor', 'pacientes')
            ->assertSeeHtmlInOrder(['Álvaro Paz', 'Ana Gómez', 'Marta Díaz', 'Zulema Ruiz', 'Bruno Sosa'])
            // Al revés pasa Bruno adelante, pero los que empatan siguen de la A a la Z.
            ->call('ordenarPor', 'pacientes')
            ->assertSeeHtmlInOrder(['Bruno Sosa', 'Álvaro Paz', 'Ana Gómez', 'Marta Díaz', 'Zulema Ruiz']);
    }

    public function test_cambiar_de_columna_arranca_de_nuevo_en_ascendente(): void
    {
        $this->listaConTresPersonas()
            ->call('ordenarPor', 'nombre') // Queda por nombre, descendente.
            ->assertSet('ascendente', false)
            ->call('ordenarPor', 'pacientes')
            ->assertSet('orden', 'pacientes')
            ->assertSet('ascendente', true);
    }

    public function test_ordenar_por_una_columna_que_no_existe_no_cambia_nada(): void
    {
        // El nombre de la columna viaja desde el navegador: se podría mandar cualquier cosa.
        $this->listaConTresPersonas()
            ->call('ordenarPor', 'pacientes')
            ->call('ordenarPor', 'pacientes') // Queda por pacientes, descendente.
            ->call('ordenarPor', 'password')
            ->assertSet('orden', 'pacientes')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Ana Gómez', 'Zulema Ruiz', 'Marta Díaz'])
            // "email" y "rol" fueron columnas de esta tabla: ya no se ordena por ellas.
            ->call('ordenarPor', 'email')
            ->assertSet('orden', 'pacientes')
            ->call('ordenarPor', 'rol')
            ->assertSet('orden', 'pacientes')
            ->assertSet('ascendente', false);
    }

    // ---------------------------------------------------------------- Listado: se recuerda durante la sesión

    public function test_el_buscador_el_filtro_y_el_orden_se_recuerdan_al_volver_a_abrir_el_listado(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $lista = $this->listaConTresPersonas();
        User::where('name', 'Ana Gómez')->firstOrFail()->disciplinas()->attach($psicologia->id);
        User::where('name', 'Zulema Ruiz')->firstOrFail()->disciplinas()->attach($psicologia->id);

        $lista->set('buscar', 'ana')
            ->set('disciplina', (string) $psicologia->id)
            ->call('ordenarPor', 'pacientes')
            ->call('ordenarPor', 'pacientes'); // Queda por pacientes, descendente.

        // Se abre de nuevo en la misma sesión (como al entrar a editar a alguien y volver):
        // la tabla sigue como se la dejó, no vuelve a los valores iniciales.
        Livewire::test(Lista::class)
            ->assertSet('buscar', 'ana')
            ->assertSet('disciplina', (string) $psicologia->id)
            ->assertSet('orden', 'pacientes')
            ->assertSet('ascendente', false)
            // Y muestra lo que corresponde: con Psicología y "ana" en el nombre, solo queda Ana.
            ->assertSee('Ana Gómez')
            ->assertDontSee('Zulema Ruiz')
            ->assertDontSee('Marta Díaz');
    }

    public function test_en_una_sesion_nueva_el_listado_abre_con_los_valores_iniciales(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);

        $this->listaConTresPersonas()
            ->set('buscar', 'ana')
            ->set('disciplina', (string) $psicologia->id)
            ->call('ordenarPor', 'pacientes');

        // Lo recordado vive en la sesión: al cerrarla (salir y volver a entrar) no queda nada.
        session()->flush();

        Livewire::test(Lista::class)
            ->assertSet('buscar', '')
            ->assertSet('disciplina', '')
            ->assertSet('orden', 'nombre')
            ->assertSet('ascendente', true);
    }
}
