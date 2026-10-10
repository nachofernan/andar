<?php

namespace Tests\Feature;

use App\Livewire\Disciplinas\Formulario;
use App\Livewire\Usuarios\Lista;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de las disciplinas: el formulario para crear y editar (una página aparte), el color de cada una
 * y el panel de disciplinas, que está en el listado "Profesionales y disciplinas" (Usuarios\Lista).
 */
class DisciplinasTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /**
     * Abre el formulario con la sesión de alguien de Dirección, que es el único rol que puede usarlo.
     * Sin $disciplina es el de alta; con $disciplina, el de edición de esa.
     * (Que un profesional no pueda entrar se prueba en RolesTest.)
     */
    private function formulario(?Disciplina $disciplina = null): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Formulario::class, $disciplina ? ['disciplina' => $disciplina] : []);
    }

    /** Abre el listado "Profesionales y disciplinas" (donde está el panel) con la sesión de alguien de Dirección. */
    private function lista(): Testable
    {
        $this->entrarComoDireccion();

        return Livewire::test(Lista::class);
    }

    // ---------------------------------------------------------------- Rutas

    public function test_sin_sesion_las_pantallas_de_disciplinas_redirigen_al_login(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $this->get('/administracion/disciplinas/crear')->assertRedirect('/login');
        $this->get("/administracion/disciplinas/{$disciplina->id}/editar")->assertRedirect('/login');
    }

    public function test_direccion_ve_la_pantalla_de_crear_disciplina(): void
    {
        $this->entrarComoDireccion();

        $this->get('/administracion/disciplinas/crear')
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            ->assertSee('Nueva disciplina');
    }

    public function test_direccion_ve_la_pantalla_de_editar_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $this->entrarComoDireccion();

        $this->get("/administracion/disciplinas/{$disciplina->id}/editar")
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            ->assertSee('Editar disciplina');
    }

    public function test_editar_una_disciplina_que_no_existe_da_404(): void
    {
        $this->entrarComoDireccion();

        $this->get('/administracion/disciplinas/9999/editar')->assertNotFound();
    }

    public function test_la_direccion_vieja_de_disciplinas_ya_no_existe(): void
    {
        $this->entrarComoDireccion();

        // Ya no hay una pantalla propia de disciplinas: el listado es el panel de /administracion/profesionales.
        $this->get('/disciplinas')->assertNotFound();
        $this->get('/administracion/disciplinas')->assertNotFound();
    }

    // ---------------------------------------------------------------- Formulario

    public function test_se_puede_crear_una_disciplina(): void
    {
        $this->formulario()
            // El formulario de alta arranca vacío.
            ->assertSet('disciplinaId', null)
            ->assertSet('nombre', '')
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasNoErrors()
            // Después de guardar vuelve al listado "Profesionales y disciplinas".
            ->assertRedirect(route('usuarios'));

        $this->assertDatabaseHas('disciplinas', ['nombre' => 'Psicología']);
    }

    public function test_al_crear_se_quitan_los_espacios_de_mas_del_nombre(): void
    {
        $this->formulario()
            ->set('nombre', '  Psicología  ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('disciplinas', ['nombre' => 'Psicología']);
    }

    public function test_el_nombre_vacio_da_error(): void
    {
        $this->formulario()
            ->set('nombre', '')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required'])
            ->assertNoRedirect(); // Con errores se queda en el formulario.

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_un_nombre_de_solo_espacios_da_error(): void
    {
        // El trim lo deja vacío, así que cae en la regla de obligatorio.
        $this->formulario()
            ->set('nombre', '   ')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_un_nombre_de_mas_de_255_caracteres_da_error(): void
    {
        $this->formulario()
            ->set('nombre', str_repeat('a', 256))
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'max']);

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_un_nombre_duplicado_da_error(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);

        $this->formulario()
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);

        $this->assertDatabaseCount('disciplinas', 1);
    }

    public function test_un_nombre_duplicado_con_espacios_de_mas_tambien_da_error(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);

        // Sin el trim, "Psicología " pasaría como una disciplina distinta.
        $this->formulario()
            ->set('nombre', 'Psicología ')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);

        $this->assertDatabaseCount('disciplinas', 1);
    }

    public function test_editar_carga_los_datos_y_guardar_actualiza_sin_crear_otra(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicologia']);

        $this->formulario($disciplina)
            ->assertSet('disciplinaId', $disciplina->id)
            ->assertSet('nombre', 'Psicologia')
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios'));

        // Sigue habiendo una sola: se actualizó la existente, no se creó otra.
        $this->assertDatabaseCount('disciplinas', 1);
        $this->assertSame('Psicología', $disciplina->fresh()->nombre);
    }

    public function test_se_puede_guardar_una_edicion_sin_cambiar_el_nombre(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        // La regla de único no cuenta a la propia disciplina que se está editando.
        $this->formulario($disciplina)
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
        $this->formulario($otra)
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);

        $this->assertSame('Fonoaudiología', $otra->fresh()->nombre);
    }

    public function test_cancelar_vuelve_al_listado_sin_guardar_nada(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        // "Cancelar" ya no es una acción del componente: es un link común al listado.
        // Lo que se haya escrito se pierde al irse, porque nada se guarda hasta apretar Guardar.
        $html = $this->formulario($disciplina)
            ->set('nombre', 'Otro nombre')
            ->assertSee('Cancelar')
            ->html();

        $this->assertStringContainsString('href="'.route('usuarios').'"', $html);
        $this->assertSame('Psicología', $disciplina->fresh()->nombre);
    }

    public function test_el_formulario_tiene_el_link_para_volver_al_listado(): void
    {
        $this->formulario()
            ->assertSeeInOrder(['href="'.route('usuarios').'"', 'Volver a profesionales y disciplinas'], false);
    }

    // ---------------------------------------------------------------- Color

    public function test_una_disciplina_nace_con_el_color_inicial(): void
    {
        $this->assertSame('#7c5cf0', Disciplina::COLOR_INICIAL);

        // Creada sin decir el color: lo tiene desde el momento de crearla y también al releerla de la base.
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $this->assertSame(Disciplina::COLOR_INICIAL, $disciplina->color);
        $this->assertSame(Disciplina::COLOR_INICIAL, $disciplina->fresh()->color);

        // Y aunque se cargue salteando el modelo, el color por defecto lo pone la columna (el mismo valor).
        DB::table('disciplinas')->insert(['nombre' => 'Fonoaudiología']);
        $this->assertSame(Disciplina::COLOR_INICIAL, Disciplina::where('nombre', 'Fonoaudiología')->firstOrFail()->color);
    }

    public function test_si_no_se_toca_el_color_la_disciplina_nueva_se_guarda_con_el_inicial(): void
    {
        $this->formulario()
            ->assertSet('color', Disciplina::COLOR_INICIAL) // El formulario de alta arranca con el violeta de la app.
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('disciplinas', ['nombre' => 'Psicología', 'color' => Disciplina::COLOR_INICIAL]);
    }

    public function test_al_crear_se_guarda_el_color_elegido(): void
    {
        $this->formulario()
            ->set('nombre', 'Psicología')
            ->set('color', '#00aa55')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios'));

        $this->assertDatabaseHas('disciplinas', ['nombre' => 'Psicología', 'color' => '#00aa55']);
    }

    public function test_el_color_escrito_en_mayusculas_o_con_espacios_se_guarda_en_minusculas(): void
    {
        // #FF8800 y #ff8800 son el mismo color: se guarda siempre de una sola forma.
        $this->formulario()
            ->set('nombre', 'Psicología')
            ->set('color', '  #FF8800 ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('#ff8800', Disciplina::firstOrFail()->color);
    }

    public function test_un_color_que_no_es_un_hexa_completo_da_error(): void
    {
        $formulario = $this->formulario()->set('nombre', 'Psicología');

        // El color termina dentro de un style="" de las pantallas: solo se acepta numeral y seis caracteres 0-9 / a-f.
        $invalidos = [
            'rojo',                  // Un nombre de color.
            '7c5cf0',                // Sin el numeral.
            '#fff',                  // Hexa corto.
            '#7c5cf',                // Le falta un carácter.
            '#7c5cf00',              // Le sobra uno.
            '#gggggg',               // Letras que no son de hexa.
            '#7c5cf0; display:none', // Con algo más pegado atrás.
        ];

        foreach ($invalidos as $color) {
            $formulario->set('color', $color)
                ->call('guardar')
                ->assertHasErrors(['color' => 'regex'])
                ->assertSee('Escribí el color en hexa, por ejemplo #7c5cf0.')
                ->assertNoRedirect(); // Con errores se queda en el formulario.
        }

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_el_color_vacio_da_error(): void
    {
        $this->formulario()
            ->set('nombre', 'Psicología')
            ->set('color', '')
            ->call('guardar')
            ->assertHasErrors(['color' => 'required']);

        $this->assertDatabaseCount('disciplinas', 0);
    }

    public function test_editar_carga_el_color_de_la_disciplina_y_guardar_lo_cambia(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología', 'color' => '#00aa55']);

        $this->formulario($disciplina)
            ->assertSet('color', '#00aa55') // El de la disciplina, no el inicial.
            ->set('color', '#FF0000')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios'));

        $disciplina->refresh();

        $this->assertDatabaseCount('disciplinas', 1);
        $this->assertSame('#ff0000', $disciplina->color);
        $this->assertSame('Psicología', $disciplina->nombre); // El nombre no se tocó.
    }

    public function test_al_editar_un_color_invalido_no_cambia_nada(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología', 'color' => '#00aa55']);

        // Tampoco se guarda el nombre nuevo: si un campo falla, no se guarda ninguno.
        $this->formulario($disciplina)
            ->set('nombre', 'Otro nombre')
            ->set('color', 'verde')
            ->call('guardar')
            ->assertHasErrors(['color' => 'regex']);

        $this->assertSame('#00aa55', $disciplina->fresh()->color);
        $this->assertSame('Psicología', $disciplina->fresh()->nombre);
    }

    public function test_cambiar_solo_el_nombre_al_editar_conserva_el_color(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicologia', 'color' => '#00aa55']);

        $this->formulario($disciplina)
            ->set('nombre', 'Psicología')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('#00aa55', $disciplina->fresh()->color);
    }

    // ---------------------------------------------------------------- Profesionales de la disciplina (al editar)

    public function test_al_editar_se_ven_los_profesionales_que_tienen_la_disciplina(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        // Se cargan desordenados: tienen que salir por nombre.
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz']);
        $zulema->disciplinas()->attach($psicologia->id);
        $ana = User::factory()->create(['name' => 'Ana Gómez']);
        $ana->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);
        // Pedro tiene otra disciplina: no tiene que aparecer.
        User::factory()->create(['name' => 'Pedro Suelto'])->disciplinas()->attach($fonoaudiologia->id);

        $this->formulario($psicologia)
            // Cada nombre es un link al formulario de ese profesional (ahí es donde se le cambian las disciplinas).
            ->assertSeeInOrder([
                'Profesionales',
                'href="'.route('usuarios.editar', $ana).'"', 'Ana Gómez',
                'href="'.route('usuarios.editar', $zulema).'"', 'Zulema Ruiz',
            ], false)
            ->assertDontSee('Pedro Suelto')
            ->assertDontSee('Todavía nadie.');
    }

    public function test_al_editar_una_disciplina_que_nadie_tiene_lo_avisa(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        User::factory()->create(['name' => 'Ana Gómez']); // Existe, pero no tiene la disciplina.

        $this->formulario($psicologia)
            ->assertSee('Todavía nadie.')
            ->assertDontSee('Ana Gómez');
    }

    public function test_al_crear_no_se_muestra_el_bloque_de_profesionales(): void
    {
        User::factory()->create(['name' => 'Ana Gómez']);

        // Una disciplina que todavía no existe no puede tener a nadie: el bloque ni aparece.
        $this->formulario()
            ->assertDontSee('Quiénes tienen esta disciplina')
            ->assertDontSee('Todavía nadie.')
            ->assertDontSee('Ana Gómez');
    }

    // ---------------------------------------------------------------- Panel de disciplinas (en el listado)

    public function test_el_panel_muestra_las_disciplinas_existentes(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);
        Disciplina::create(['nombre' => 'Fonoaudiología']);

        $this->lista()
            // El panel sale ordenado por nombre.
            ->assertSeeInOrder(['Fonoaudiología', 'Psicología'])
            ->assertDontSee('Todavía no hay disciplinas cargadas.');
    }

    public function test_el_panel_cuenta_en_cada_disciplina_al_profesional_que_tiene_dos(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        Disciplina::create(['nombre' => 'Psicomotricidad']); // Nadie la tiene.
        User::factory()->create()->disciplinas()->attach($psicologia->id);
        // Tiene dos: tiene que sumar uno en cada una.
        User::factory()->create()->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);

        // El número al lado de cada disciplina es profesionales_count.
        // Se lee el dato que recibe la vista: un número suelto en el HTML se confundiría con cualquier otro.
        $lista = $this->lista();
        $cantidades = $lista->viewData('disciplinas')->pluck('profesionales_count', 'nombre')->all();

        $this->assertSame(['Fonoaudiología' => 1, 'Psicología' => 2, 'Psicomotricidad' => 0], $cantidades);

        // Y en la pantalla cada número va pegado al nombre de su disciplina.
        $lista->assertSeeInOrder(['Fonoaudiología', '>1</span>', 'Psicología', '>2</span>', 'Psicomotricidad', '>0</span>'], false);
    }

    public function test_el_panel_no_cambia_la_cantidad_al_filtrar_la_tabla(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        User::factory()->create()->disciplinas()->attach($psicologia->id);
        User::factory()->create()->disciplinas()->attach($fonoaudiologia->id);

        // El panel cuenta a todos los profesionales, no solo a los que quedaron en la tabla.
        $cantidades = $this->lista()
            ->set('disciplina', (string) $psicologia->id)
            ->set('buscar', 'nadie-se-llama-asi')
            ->viewData('disciplinas')
            ->pluck('profesionales_count', 'nombre')
            ->all();

        $this->assertSame(['Fonoaudiología' => 1, 'Psicología' => 1], $cantidades);
    }

    public function test_sin_disciplinas_el_panel_avisa_que_esta_vacio(): void
    {
        $this->lista()->assertSee('Todavía no hay disciplinas cargadas.');
    }

    public function test_una_disciplina_recien_creada_aparece_en_el_panel(): void
    {
        $this->formulario()
            ->set('nombre', 'Psicopedagogía')
            ->call('guardar')
            ->assertRedirect(route('usuarios'));

        // Al volver al listado ya está en el panel.
        Livewire::test(Lista::class)->assertSee('Psicopedagogía');
    }

    public function test_en_el_panel_cada_disciplina_es_un_link_a_su_pantalla_y_hay_un_boton_para_crear(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $html = $this->lista()
            // El renglón entero es el link: primero la dirección y adentro el nombre.
            ->assertSeeInOrder(['href="'.route('disciplinas.editar', $disciplina).'"', 'Psicología'], false)
            ->assertSeeInOrder(['href="'.route('disciplinas.crear').'"', 'Nueva disciplina'], false)
            ->html();

        // Ya no hay un link "Editar" aparte, y cada disciplina tiene un solo link.
        $this->assertStringNotContainsString('Editar', $html);
        $this->assertSame(1, substr_count($html, 'href="'.route('disciplinas.editar', $disciplina).'"'));
    }

    public function test_en_el_panel_cada_disciplina_lleva_un_puntito_de_su_color(): void
    {
        Disciplina::create(['nombre' => 'Psicología', 'color' => '#ff0000']);
        Disciplina::create(['nombre' => 'Fonoaudiología']); // Sin color elegido: el inicial.

        // El color viene de la base, por eso va en style. Nadie tiene estas disciplinas,
        // así que los únicos colores de la pantalla son los del panel.
        $html = $this->lista()
            ->assertSeeHtml('style="background-color: #ff0000" title="Psicología"')
            ->assertSeeHtml('style="background-color: '.Disciplina::COLOR_INICIAL.'" title="Fonoaudiología"')
            ->html();

        $this->assertSame(2, substr_count($html, 'style="background-color:'));
    }

    public function test_un_color_recien_cambiado_se_ve_en_el_panel_y_en_la_etiqueta_del_profesional(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        User::factory()->create(['name' => 'Ana Gómez'])->disciplinas()->attach($disciplina->id);

        $this->formulario($disciplina)->set('color', '#00AA55')->call('guardar')->assertHasNoErrors();

        // De punta a punta: lo que se guardó (en minúsculas) es lo que pinta el puntito del panel y la etiqueta de la tabla.
        Livewire::test(Lista::class)
            ->assertSeeHtml('style="background-color: #00aa55" title="Psicología"')
            ->assertSeeHtml('style="color: #00aa55; background-color: #00aa551a">Psicología</span>');
    }
}
