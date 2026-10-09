<?php

namespace Tests\Feature;

use App\Livewire\Usuarios;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** Pruebas de la pantalla de usuarios (los profesionales): listar, crear y editar. */
class UsuariosTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /**
     * Abre la pantalla con la sesión de alguien de Dirección, que es el único rol que puede usarla.
     * (Que un profesional no pueda entrar se prueba en RolesTest.)
     *
     * Ojo al contar usuarios: quien tiene la sesión también es una fila de la tabla users.
     */
    private function pantalla(): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Usuarios::class);
    }

    public function test_sin_sesion_usuarios_redirige_al_login(): void
    {
        $this->get('/usuarios')->assertRedirect('/login');
    }

    public function test_direccion_ve_la_pantalla_de_usuarios(): void
    {
        $this->entrarComoDireccion();

        $this->get('/usuarios')
            ->assertOk()
            ->assertSeeLivewire(Usuarios::class);
    }

    public function test_se_puede_crear_un_usuario_con_una_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        Disciplina::create(['nombre' => 'Fonoaudiología']); // No se tilda: no tiene que quedar vinculada.

        $this->pantalla()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            // Las casillas mandan los ids como texto, por eso se pasan como string.
            ->set('disciplinas', [(string) $disciplina->id])
            ->call('guardar')
            ->assertHasNoErrors()
            // Después de guardar el formulario queda vacío, listo para cargar otro.
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('disciplinas', [])
            ->assertSet('editandoId', null);

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

        $this->pantalla()
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
        $this->pantalla()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertCount(0, User::where('email', 'ana@andar.test')->firstOrFail()->disciplinas);
        $this->assertDatabaseCount('disciplina_user', 0);
    }

    public function test_el_usuario_nuevo_puede_entrar_con_la_contrasena_inicial(): void
    {
        $this->pantalla()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'ana@andar.test')->firstOrFail();

        $this->assertSame('password', Usuarios::CONTRASENA_INICIAL);
        // En la base queda encriptada, nunca el texto tal cual.
        $this->assertNotSame('password', $usuario->password);
        $this->assertTrue(Hash::check('password', $usuario->password));
        $this->assertTrue(Auth::attempt(['email' => 'ana@andar.test', 'password' => 'password']));
    }

    public function test_al_crear_se_quitan_los_espacios_de_mas_de_nombre_y_email(): void
    {
        $this->pantalla()
            ->set('name', '  Ana Gómez  ')
            ->set('email', '  ana@andar.test  ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);
    }

    public function test_un_email_duplicado_da_error(): void
    {
        User::factory()->create(['email' => 'ana@andar.test']);

        $this->pantalla()
            ->set('name', 'Otra Ana')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique']);

        // No se creó otro: siguen siendo Ana y quien tiene la sesión.
        $this->assertDatabaseCount('users', 2);
    }

    public function test_un_email_invalido_da_error(): void
    {
        $this->pantalla()
            ->set('name', 'Ana Gómez')
            ->set('email', 'esto-no-es-un-email')
            ->call('guardar')
            ->assertHasErrors(['email' => 'email']);

        $this->assertDatabaseCount('users', 1); // Solo quien tiene la sesión: no se creó nadie.
    }

    public function test_el_email_vacio_da_error(): void
    {
        $this->pantalla()
            ->set('name', 'Ana Gómez')
            ->set('email', '')
            ->call('guardar')
            ->assertHasErrors(['email' => 'required']);

        $this->assertDatabaseCount('users', 1); // Solo quien tiene la sesión: no se creó nadie.
    }

    public function test_el_nombre_vacio_da_error(): void
    {
        $this->pantalla()
            ->set('name', '')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseCount('users', 1); // Solo quien tiene la sesión: no se creó nadie.
    }

    public function test_una_disciplina_inexistente_da_error(): void
    {
        $this->pantalla()
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

    public function test_editar_carga_los_datos_del_usuario_con_sus_disciplinas_tildadas(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        Disciplina::create(['nombre' => 'Psicomotricidad']); // Existe, pero el usuario no la tiene.
        $usuario = User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);
        $usuario->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);

        $this->pantalla()
            ->call('editar', $usuario->id)
            ->assertSet('editandoId', $usuario->id)
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

        $this->pantalla()
            ->call('editar', $usuario->id)
            ->assertSet('disciplinas', []);
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

        $this->pantalla()
            ->call('editar', $usuario->id)
            ->set('name', 'Ana María Gómez')
            ->set('email', 'anamaria@andar.test')
            // Se destilda Psicología, queda Fonoaudiología y se tilda Psicomotricidad.
            ->set('disciplinas', [(string) $fonoaudiologia->id, (string) $psicomotricidad->id])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('disciplinas', [])
            ->assertSet('editandoId', null);

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
        $this->assertFalse(Hash::check(Usuarios::CONTRASENA_INICIAL, $usuario->password));
    }

    public function test_se_puede_guardar_una_edicion_sin_cambiar_el_email(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);

        // La regla de único no cuenta al propio usuario que se está editando.
        $this->pantalla()
            ->call('editar', $usuario->id)
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
        $this->pantalla()
            ->call('editar', $otro->id)
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

        $this->pantalla()
            ->call('editar', $usuario->id)
            ->set('disciplinas', []) // Todas las casillas destildadas.
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('disciplina_user', 0);
        // Se le sacan los vínculos, pero las disciplinas siguen existiendo.
        $this->assertDatabaseCount('disciplinas', 2);
    }

    public function test_cancelar_limpia_el_formulario(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $usuario = User::factory()->create(['name' => 'Ana Gómez']);
        $usuario->disciplinas()->attach($disciplina->id);

        $this->pantalla()
            ->call('editar', $usuario->id)
            ->set('name', 'Otro nombre')
            ->set('disciplinas', [])
            ->call('cancelar')
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('disciplinas', [])
            ->assertSet('editandoId', null);

        // Cancelar no guarda nada: ni el nombre ni las disciplinas destildadas.
        $this->assertSame('Ana Gómez', $usuario->fresh()->name);
        $this->assertDatabaseCount('disciplina_user', 1);
    }

    public function test_la_lista_muestra_los_usuarios_con_todas_sus_disciplinas(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz', 'email' => 'zulema@andar.test']);
        $zulema->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);
        User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']); // Sin disciplina.

        $this->pantalla()
            // La lista sale ordenada por nombre. Quien no tiene disciplina lleva una raya,
            // y quien tiene varias las muestra separadas por coma, en orden alfabético.
            // (Los nombres sueltos también están en las casillas del formulario; juntos y con coma, solo en la lista.)
            ->assertSeeInOrder([
                'Ana Gómez', 'ana@andar.test', '—',
                'Zulema Ruiz', 'zulema@andar.test', 'Fonoaudiología, Psicología',
            ]);
    }

    // ---------------------------------------------------------------- Rol

    public function test_un_usuario_nuevo_queda_como_profesional_si_no_se_toca_el_rol(): void
    {
        $this->pantalla()
            ->assertSet('rol', 'profesional') // El desplegable arranca en "Profesional".
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasNoErrors();

        // Un solo rol: el rol no es una columna del usuario, lo guarda Spatie Permission en sus tablas.
        $this->assertSame(['profesional'], User::where('email', 'ana@andar.test')->firstOrFail()->getRoleNames()->all());
    }

    public function test_se_puede_crear_un_usuario_de_direccion(): void
    {
        $this->pantalla()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('rol', 'direccion')
            ->call('guardar')
            ->assertHasNoErrors()
            // Después de guardar el desplegable vuelve a "Profesional", listo para cargar otro.
            ->assertSet('rol', 'profesional');

        $usuario = User::where('email', 'ana@andar.test')->firstOrFail();

        $this->assertSame(['direccion'], $usuario->getRoleNames()->all());
        $this->assertTrue($usuario->esDireccion());
    }

    public function test_un_rol_que_no_esta_en_la_lista_da_error(): void
    {
        $this->pantalla()
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('rol', 'superadmin') // No existe: el desplegable no lo ofrece, pero se podría mandar igual.
            ->call('guardar')
            ->assertHasErrors(['rol' => 'in'])
            ->assertSee('Elegí un rol de la lista.');

        $this->assertDatabaseMissing('users', ['email' => 'ana@andar.test']);
    }

    public function test_editar_carga_el_rol_del_usuario_y_guardar_lo_cambia(): void
    {
        $usuario = User::factory()->create(); // Profesional.

        $pantalla = $this->pantalla()
            ->call('editar', $usuario->id)
            ->assertSet('rol', 'profesional')
            ->set('rol', 'direccion')
            ->call('guardar')
            ->assertHasNoErrors();

        // Queda solo con el rol nuevo: no acumula el anterior.
        $this->assertSame(['direccion'], $usuario->fresh()->getRoleNames()->all());

        // Y al volver a abrirlo, el desplegable ya muestra "Dirección".
        $pantalla->call('editar', $usuario->id)->assertSet('rol', 'direccion');
    }

    public function test_no_se_puede_sacar_el_rol_direccion_a_la_unica_persona_de_direccion(): void
    {
        // Quien tiene la sesión es la única persona de Dirección. Hay un profesional, pero no cuenta.
        $direccion = $this->entrarComoDireccion();
        User::factory()->create();

        // Si se pudiera, el centro quedaría sin nadie que cargue usuarios ni pacientes.
        Livewire::test(Usuarios::class)
            ->call('editar', $direccion->id)
            ->set('rol', 'profesional')
            ->call('guardar')
            ->assertHasErrors('rol')
            ->assertSee('Es la única persona de Dirección: antes hay que darle ese rol a otra.');

        $this->assertSame(['direccion'], $direccion->fresh()->getRoleNames()->all());
    }

    public function test_la_unica_persona_de_direccion_puede_editar_sus_otros_datos(): void
    {
        $direccion = $this->entrarComoDireccion();

        // La regla solo frena el cambio de rol: mientras siga en Dirección, el resto se guarda normal.
        Livewire::test(Usuarios::class)
            ->call('editar', $direccion->id)
            ->set('name', 'Nombre Nuevo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Nombre Nuevo', $direccion->fresh()->name);
        $this->assertTrue($direccion->fresh()->esDireccion());
    }

    public function test_se_puede_sacar_el_rol_direccion_cuando_hay_otra_persona_de_direccion(): void
    {
        $this->entrarComoDireccion();
        $otra = User::factory()->direccion()->create();

        Livewire::test(Usuarios::class)
            ->call('editar', $otra->id)
            ->set('rol', 'profesional')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(['profesional'], $otra->fresh()->getRoleNames()->all());
    }

    public function test_la_lista_muestra_el_rol_de_cada_usuario(): void
    {
        $this->entrarComoDireccion(['name' => 'Zulema Ruiz', 'email' => 'zulema@andar.test']);
        User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);

        // Se muestra el nombre lindo del rol ("Dirección"), no el que se guarda en la base ("direccion").
        // Esas palabras también están en el desplegable, que va más arriba: por eso se pide el orden,
        // después del email de cada persona solo puede ser su columna Rol.
        Livewire::test(Usuarios::class)
            ->assertSeeInOrder([
                'Ana Gómez', 'ana@andar.test', 'Profesional',
                'Zulema Ruiz', 'zulema@andar.test', 'Dirección',
            ]);
    }
}
