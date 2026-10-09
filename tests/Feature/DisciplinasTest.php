<?php

namespace Tests\Feature;

use App\Livewire\Disciplinas;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** Pruebas de la pantalla de disciplinas: listar, crear y editar. */
class DisciplinasTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /**
     * Abre la pantalla con la sesión de alguien de Dirección, que es el único rol que puede usarla.
     * (Que un profesional no pueda entrar se prueba en RolesTest.)
     */
    private function pantalla(): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Disciplinas::class);
    }

    public function test_sin_sesion_disciplinas_redirige_al_login(): void
    {
        $this->get('/disciplinas')->assertRedirect('/login');
    }

    public function test_direccion_ve_la_pantalla_de_disciplinas(): void
    {
        $this->entrarComoDireccion();

        $this->get('/disciplinas')
            ->assertOk()
            ->assertSeeLivewire(Disciplinas::class);
    }

    public function test_se_puede_crear_una_disciplina(): void
    {
        $this->pantalla()
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasNoErrors()
            // Después de guardar el formulario queda vacío, listo para cargar otra.
            ->assertSet('nombre', '')
            ->assertSet('editandoId', null);

        $this->assertDatabaseHas('disciplinas', ['nombre' => 'Psicología']);
    }

    public function test_al_crear_se_quitan_los_espacios_de_mas_del_nombre(): void
    {
        $this->pantalla()
            ->set('nombre', '  Psicología  ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('disciplinas', ['nombre' => 'Psicología']);
    }

    public function test_el_nombre_vacio_da_error(): void
    {
        $this->pantalla()
            ->set('nombre', '')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_un_nombre_de_solo_espacios_da_error(): void
    {
        // El trim lo deja vacío, así que cae en la regla de obligatorio.
        $this->pantalla()
            ->set('nombre', '   ')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_un_nombre_de_mas_de_255_caracteres_da_error(): void
    {
        $this->pantalla()
            ->set('nombre', str_repeat('a', 256))
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'max']);

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_un_nombre_duplicado_da_error(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);

        $this->pantalla()
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);

        $this->assertDatabaseCount('disciplinas', 1);
    }

    public function test_un_nombre_duplicado_con_espacios_de_mas_tambien_da_error(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);

        // Sin el trim, "Psicología " pasaría como una disciplina distinta.
        $this->pantalla()
            ->set('nombre', 'Psicología ')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);

        $this->assertDatabaseCount('disciplinas', 1);
    }

    public function test_editar_carga_los_datos_y_guardar_actualiza_sin_crear_otra(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicologia']);

        $this->pantalla()
            ->call('editar', $disciplina->id)
            ->assertSet('editandoId', $disciplina->id)
            ->assertSet('nombre', 'Psicologia')
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('nombre', '')
            ->assertSet('editandoId', null);

        // Sigue habiendo una sola: se actualizó la existente, no se creó otra.
        $this->assertDatabaseCount('disciplinas', 1);
        $this->assertSame('Psicología', $disciplina->fresh()->nombre);
    }

    public function test_se_puede_guardar_una_edicion_sin_cambiar_el_nombre(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        // La regla de único no cuenta a la propia disciplina que se está editando.
        $this->pantalla()
            ->call('editar', $disciplina->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('disciplinas', 1);
        $this->assertSame('Psicología', $disciplina->fresh()->nombre);
    }

    public function test_al_editar_no_se_puede_usar_el_nombre_de_otra_disciplina(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);
        $otra = Disciplina::create(['nombre' => 'Fonoaudiología']);

        // La regla de único solo ignora a la que se edita, no a las demás.
        $this->pantalla()
            ->call('editar', $otra->id)
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);

        $this->assertSame('Fonoaudiología', $otra->fresh()->nombre);
    }

    public function test_cancelar_limpia_el_formulario(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $this->pantalla()
            ->call('editar', $disciplina->id)
            ->set('nombre', 'Otro nombre')
            ->call('cancelar')
            ->assertSet('nombre', '')
            ->assertSet('editandoId', null);

        // Cancelar no guarda nada.
        $this->assertSame('Psicología', $disciplina->fresh()->nombre);
    }

    public function test_cancelar_borra_los_errores_de_validacion(): void
    {
        $this->pantalla()
            ->call('guardar')
            ->assertHasErrors('nombre')
            ->call('cancelar')
            ->assertHasNoErrors();
    }

    public function test_la_lista_muestra_las_disciplinas_existentes(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);
        Disciplina::create(['nombre' => 'Fonoaudiología']);

        $this->pantalla()
            // La lista sale ordenada por nombre.
            ->assertSeeInOrder(['Fonoaudiología', 'Psicología'])
            ->assertDontSee('Todavía no hay disciplinas cargadas.');
    }

    public function test_la_lista_cuenta_en_cada_disciplina_al_profesional_que_tiene_dos(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        Disciplina::create(['nombre' => 'Psicomotricidad']); // Nadie la tiene.
        User::factory()->create()->disciplinas()->attach($psicologia->id);
        // Tiene dos: tiene que sumar uno en cada una.
        User::factory()->create()->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);

        // La columna "Profesionales" de la lista muestra profesionales_count.
        // Se lee el dato que recibe la vista: un número suelto en el HTML se confundiría con cualquier otro.
        $cantidades = $this->pantalla()
            ->viewData('disciplinas')
            ->pluck('profesionales_count', 'nombre')
            ->all();

        $this->assertSame(['Fonoaudiología' => 1, 'Psicología' => 2, 'Psicomotricidad' => 0], $cantidades);
    }

    public function test_sin_disciplinas_la_lista_avisa_que_esta_vacia(): void
    {
        $this->pantalla()->assertSee('Todavía no hay disciplinas cargadas.');
    }

    public function test_una_disciplina_recien_creada_aparece_en_la_lista(): void
    {
        $this->pantalla()
            ->set('nombre', 'Psicopedagogía')
            ->call('guardar')
            ->assertSee('Psicopedagogía');
    }
}
