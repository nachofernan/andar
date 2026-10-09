<?php

namespace Tests\Feature;

use App\Livewire\Usuarios;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Pruebas de la pantalla de usuarios (los profesionales): listar, crear y editar. */
class UsuariosTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    public function test_sin_sesion_usuarios_redirige_al_login(): void
    {
        $this->get('/usuarios')->assertRedirect('/login');
    }

    public function test_con_sesion_la_pantalla_de_usuarios_se_muestra(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/usuarios')
            ->assertOk()
            ->assertSeeLivewire(Usuarios::class);
    }

    public function test_se_puede_crear_un_usuario_con_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        Livewire::test(Usuarios::class)
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            // El desplegable manda el id como texto, por eso se pasa como string.
            ->set('disciplina_id', (string) $disciplina->id)
            ->call('guardar')
            ->assertHasNoErrors()
            // Después de guardar el formulario queda vacío, listo para cargar otro.
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('disciplina_id', '')
            ->assertSet('editandoId', null);

        $this->assertDatabaseHas('users', [
            'name' => 'Ana Gómez',
            'email' => 'ana@andar.test',
            'disciplina_id' => $disciplina->id,
        ]);
    }

    public function test_se_puede_crear_un_usuario_sin_disciplina(): void
    {
        Livewire::test(Usuarios::class)
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('disciplina_id', '')
            ->call('guardar')
            ->assertHasNoErrors();

        // '' (opción "Sin disciplina") se guarda como null, no como texto vacío.
        $this->assertNull(User::where('email', 'ana@andar.test')->firstOrFail()->disciplina_id);
    }

    public function test_el_usuario_nuevo_puede_entrar_con_la_contrasena_inicial(): void
    {
        Livewire::test(Usuarios::class)
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
        Livewire::test(Usuarios::class)
            ->set('name', '  Ana Gómez  ')
            ->set('email', '  ana@andar.test  ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);
    }

    public function test_un_email_duplicado_da_error(): void
    {
        User::factory()->create(['email' => 'ana@andar.test']);

        Livewire::test(Usuarios::class)
            ->set('name', 'Otra Ana')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_un_email_invalido_da_error(): void
    {
        Livewire::test(Usuarios::class)
            ->set('name', 'Ana Gómez')
            ->set('email', 'esto-no-es-un-email')
            ->call('guardar')
            ->assertHasErrors(['email' => 'email']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_email_vacio_da_error(): void
    {
        Livewire::test(Usuarios::class)
            ->set('name', 'Ana Gómez')
            ->set('email', '')
            ->call('guardar')
            ->assertHasErrors(['email' => 'required']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_nombre_vacio_da_error(): void
    {
        Livewire::test(Usuarios::class)
            ->set('name', '')
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_una_disciplina_inexistente_da_error(): void
    {
        Livewire::test(Usuarios::class)
            ->set('name', 'Ana Gómez')
            ->set('email', 'ana@andar.test')
            ->set('disciplina_id', '999') // No hay ninguna disciplina con ese id.
            ->call('guardar')
            ->assertHasErrors(['disciplina_id' => 'exists']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_editar_carga_los_datos_del_usuario(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $usuario = User::factory()->create([
            'name' => 'Ana Gómez',
            'email' => 'ana@andar.test',
            'disciplina_id' => $disciplina->id,
        ]);

        Livewire::test(Usuarios::class)
            ->call('editar', $usuario->id)
            ->assertSet('editandoId', $usuario->id)
            ->assertSet('name', 'Ana Gómez')
            ->assertSet('email', 'ana@andar.test')
            ->assertSet('disciplina_id', (string) $disciplina->id);
    }

    public function test_editar_un_usuario_sin_disciplina_deja_el_desplegable_vacio(): void
    {
        $usuario = User::factory()->create(['disciplina_id' => null]);

        // null se convierte en '' para que el desplegable marque "Sin disciplina".
        Livewire::test(Usuarios::class)
            ->call('editar', $usuario->id)
            ->assertSet('disciplina_id', '');
    }

    public function test_guardar_una_edicion_actualiza_los_datos_sin_cambiar_la_contrasena(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $usuario = User::factory()->create([
            'name' => 'Ana Gómez',
            'email' => 'ana@andar.test',
            'password' => 'clave-propia',
            'disciplina_id' => $psicologia->id,
        ]);
        $hashAntes = $usuario->fresh()->password;

        Livewire::test(Usuarios::class)
            ->call('editar', $usuario->id)
            ->set('name', 'Ana María Gómez')
            ->set('email', 'anamaria@andar.test')
            ->set('disciplina_id', (string) $fonoaudiologia->id)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('disciplina_id', '')
            ->assertSet('editandoId', null);

        $usuario->refresh();

        // Se actualizó el existente, no se creó otro.
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Ana María Gómez', $usuario->name);
        $this->assertSame('anamaria@andar.test', $usuario->email);
        $this->assertSame($fonoaudiologia->id, $usuario->disciplina_id);

        // El hash es exactamente el mismo: ni se pisó con la contraseña inicial ni se volvió a encriptar.
        $this->assertSame($hashAntes, $usuario->password);
        $this->assertTrue(Hash::check('clave-propia', $usuario->password));
        $this->assertFalse(Hash::check(Usuarios::CONTRASENA_INICIAL, $usuario->password));
    }

    public function test_se_puede_guardar_una_edicion_sin_cambiar_el_email(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);

        // La regla de único no cuenta al propio usuario que se está editando.
        Livewire::test(Usuarios::class)
            ->call('editar', $usuario->id)
            ->set('name', 'Ana María Gómez')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario->refresh();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Ana María Gómez', $usuario->name);
        $this->assertSame('ana@andar.test', $usuario->email);
    }

    public function test_al_editar_no_se_puede_usar_el_email_de_otro_usuario(): void
    {
        User::factory()->create(['email' => 'ana@andar.test']);
        $otro = User::factory()->create(['email' => 'beto@andar.test']);

        // La regla de único solo ignora al que se edita, no a los demás.
        Livewire::test(Usuarios::class)
            ->call('editar', $otro->id)
            ->set('email', 'ana@andar.test')
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertSame('beto@andar.test', $otro->fresh()->email);
    }

    public function test_pasar_de_tener_disciplina_a_sin_disciplina_deja_null(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $usuario = User::factory()->create(['disciplina_id' => $disciplina->id]);

        Livewire::test(Usuarios::class)
            ->call('editar', $usuario->id)
            ->set('disciplina_id', '') // Opción "Sin disciplina" del desplegable.
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull($usuario->fresh()->disciplina_id);
    }

    public function test_cancelar_limpia_el_formulario(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $usuario = User::factory()->create(['name' => 'Ana Gómez', 'disciplina_id' => $disciplina->id]);

        Livewire::test(Usuarios::class)
            ->call('editar', $usuario->id)
            ->set('name', 'Otro nombre')
            ->call('cancelar')
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('disciplina_id', '')
            ->assertSet('editandoId', null);

        // Cancelar no guarda nada.
        $this->assertSame('Ana Gómez', $usuario->fresh()->name);
    }

    public function test_la_lista_muestra_los_usuarios_con_su_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        User::factory()->create(['name' => 'Zulema Ruiz', 'email' => 'zulema@andar.test', 'disciplina_id' => $disciplina->id]);
        User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@andar.test']);

        Livewire::test(Usuarios::class)
            // La lista sale ordenada por nombre.
            ->assertSeeInOrder(['Ana Gómez', 'ana@andar.test', 'Zulema Ruiz', 'zulema@andar.test', 'Psicología']);
    }
}
