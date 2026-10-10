<?php

namespace Tests\Feature;

use App\Livewire\Perfil;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Pruebas de "Mi perfil": cada usuario cambia sus propios datos y, si quiere, su contraseña. */
class PerfilTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    public function test_sin_sesion_la_pantalla_redirige_al_login(): void
    {
        $this->get('/mi-perfil')->assertRedirect('/login');
    }

    public function test_cualquier_rol_puede_abrir_su_perfil(): void
    {
        $this->actingAs(User::factory()->create())->get('/mi-perfil')->assertOk();
        $this->actingAs(User::factory()->direccion()->create())->get('/mi-perfil')->assertOk();
    }

    public function test_el_formulario_arranca_con_los_datos_actuales_del_usuario(): void
    {
        $usuario = User::factory()->create(['name' => 'Lucía Pérez', 'email' => 'lucia@andar.test', 'telefono' => '1122334455']);

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->assertSet('name', 'Lucía Pérez')
            ->assertSet('email', 'lucia@andar.test')
            ->assertSet('telefono', '1122334455');
    }

    public function test_se_puede_cambiar_nombre_email_y_telefono(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('name', 'Nuevo Nombre')
            ->set('email', 'nuevo@andar.test')
            ->set('telefono', '1155667788')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario->refresh();
        $this->assertSame('Nuevo Nombre', $usuario->name);
        $this->assertSame('nuevo@andar.test', $usuario->email);
        $this->assertSame('1155667788', $usuario->telefono);
    }

    public function test_dejar_el_telefono_vacio_lo_guarda_como_null(): void
    {
        $usuario = User::factory()->create(['telefono' => '1122334455']);

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('telefono', '')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull($usuario->refresh()->telefono);
    }

    public function test_el_nombre_vacio_da_error(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('name', '')
            ->call('guardar')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_el_email_de_otro_usuario_da_error(): void
    {
        User::factory()->create(['email' => 'ocupado@andar.test']);
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('email', 'ocupado@andar.test')
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_el_usuario_puede_guardar_quedandose_con_su_propio_email(): void
    {
        $usuario = User::factory()->create(['email' => 'lucia@andar.test']);

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('name', 'Otro nombre')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('lucia@andar.test', $usuario->refresh()->email);
    }

    public function test_cambiar_la_contrasena_con_la_actual_correcta_la_actualiza(): void
    {
        $usuario = User::factory()->create(['password' => 'password']);

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('contrasena_actual', 'password')
            ->set('contrasena_nueva', 'nueva1234')
            ->set('contrasena_nueva_confirmation', 'nueva1234')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('nueva1234', $usuario->refresh()->password));
        $this->assertTrue(Auth::attempt(['email' => $usuario->email, 'password' => 'nueva1234']));
    }

    public function test_cambiar_la_contrasena_con_la_actual_incorrecta_no_guarda_nada(): void
    {
        $usuario = User::factory()->create(['password' => 'password', 'name' => 'Nombre Original']);
        $hashOriginal = $usuario->password;

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('name', 'Nombre Que No Debería Guardarse')
            ->set('contrasena_actual', 'mal')
            ->set('contrasena_nueva', 'nueva1234')
            ->set('contrasena_nueva_confirmation', 'nueva1234')
            ->call('guardar')
            ->assertHasErrors('contrasena_actual');

        $usuario->refresh();
        $this->assertSame($hashOriginal, $usuario->password);
        $this->assertSame('Nombre Original', $usuario->name); // Guardado todo o nada.
    }

    public function test_las_contrasenas_nuevas_que_no_coinciden_dan_error(): void
    {
        $usuario = User::factory()->create(['password' => 'password']);

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('contrasena_actual', 'password')
            ->set('contrasena_nueva', 'nueva1234')
            ->set('contrasena_nueva_confirmation', 'otra-cosa')
            ->call('guardar')
            ->assertHasErrors(['contrasena_nueva' => 'confirmed']);
    }

    public function test_una_contrasena_nueva_corta_da_error(): void
    {
        $usuario = User::factory()->create(['password' => 'password']);

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('contrasena_actual', 'password')
            ->set('contrasena_nueva', 'corta')
            ->set('contrasena_nueva_confirmation', 'corta')
            ->call('guardar')
            ->assertHasErrors(['contrasena_nueva' => 'min']);
    }

    public function test_dejar_los_campos_de_contrasena_vacios_no_la_cambia(): void
    {
        $usuario = User::factory()->create(['password' => 'password']);
        $hashOriginal = $usuario->password;

        Livewire::actingAs($usuario)
            ->test(Perfil::class)
            ->set('name', 'Otro nombre')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($hashOriginal, $usuario->refresh()->password);
    }

    // ---------------------------------------------------------------- Tarjeta "En el centro" (solo lectura)

    public function test_la_tarjeta_en_el_centro_muestra_a_un_profesional_su_rol_y_sus_disciplinas_con_color(): void
    {
        $usuario = User::factory()->create();
        $usuario->disciplinas()->attach([
            Disciplina::create(['nombre' => 'Psicología', 'color' => '#aa0000'])->id,
            Disciplina::create(['nombre' => 'Fonoaudiología', 'color' => '#00aa00'])->id,
        ]);

        $html = Livewire::actingAs($usuario)
            ->test(Perfil::class)
            // Una etiqueta por disciplina, en orden alfabético (se cargaron al revés a propósito).
            ->assertSeeInOrder(['En el centro', 'Rol', '>Profesional</span>', 'Disciplinas', 'Fonoaudiología', 'Psicología'], false)
            ->assertSeeHtml('style="color: #00aa00; background-color: #00aa001a">Fonoaudiología</span>')
            ->assertSeeHtml('style="color: #aa0000; background-color: #aa00001a">Psicología</span>')
            ->html();

        $this->assertStringNotContainsString('>Admin</span>', $html);
        // Es solo para mirar: el rol y las disciplinas no tienen ningún control conectado al componente.
        $this->assertStringNotContainsString('wire:model="disciplinas"', $html);
        $this->assertStringNotContainsString('wire:model="admin"', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html);
    }

    public function test_la_tarjeta_en_el_centro_le_dice_admin_a_quien_es_de_direccion(): void
    {
        $html = Livewire::actingAs(User::factory()->direccion()->create())
            ->test(Perfil::class)
            ->assertSeeInOrder(['En el centro', 'Rol', '>Admin</span>'], false)
            ->html();

        $this->assertStringNotContainsString('>Profesional</span>', $html);
    }

    public function test_la_tarjeta_en_el_centro_muestra_una_raya_si_no_tiene_disciplinas(): void
    {
        $html = Livewire::actingAs(User::factory()->create())
            ->test(Perfil::class)
            ->assertSeeInOrder(['Disciplinas', '—'])
            ->html();

        // Es la única raya de la pantalla.
        $this->assertSame(1, substr_count($html, '—'));
    }
}
