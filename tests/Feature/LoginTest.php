<?php

namespace Tests\Feature;

use App\Livewire\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Pruebas del inicio y cierre de sesión. */
class LoginTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_la_pantalla_de_login_se_muestra(): void
    {
        $this->get('/login')->assertOk()->assertSeeLivewire(Login::class);
    }

    public function test_con_sesion_el_inicio_muestra_el_nombre_del_usuario(): void
    {
        $user = User::factory()->create(['name' => 'Catalina Arnal']);

        // Confirma que la vista inicio renderiza completa con el layout <x-layouts::app>.
        $this->actingAs($user)->get('/')->assertOk()->assertSee('Hola, Catalina Arnal');
    }

    public function test_login_correcto_lleva_al_inicio(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secreto123')
            ->call('entrar')
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_con_clave_incorrecta_muestra_error(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'incorrecta')
            ->call('entrar')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_exige_email_y_clave(): void
    {
        // Se vacían a mano porque el login viene precargado con el usuario de ejemplo.
        Livewire::test(Login::class)
            ->set('email', '')
            ->set('password', '')
            ->call('entrar')
            ->assertHasErrors(['email', 'password']);
    }

    public function test_logout_cierra_la_sesion(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
