<?php

namespace Tests\Feature;

use App\Livewire\Disciplinas\Formulario;
use App\Livewire\Usuarios\Lista;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            // El formulario ya no lleva título: se reconoce por el link de volver al listado.
            ->assertSeeInOrder(['href="'.route('usuarios').'"', 'Volver a profesionales y disciplinas'], false);
    }

    public function test_direccion_ve_la_pantalla_de_editar_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $this->entrarComoDireccion();

        $this->get("/administracion/disciplinas/{$disciplina->id}/editar")
            ->assertOk()
            ->assertSeeLivewire(Formulario::class)
            // El formulario ya no lleva título: se reconoce por el link de volver al listado.
            // (Que abre con los datos de la disciplina ya cargados se prueba más abajo, en "Editar".)
            ->assertSeeInOrder(['href="'.route('usuarios').'"', 'Volver a profesionales y disciplinas'], false);
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

    // ---------------------------------------------------------------- Profesionales de la disciplina

    public function test_el_formulario_muestra_una_casilla_por_cada_usuario_ordenados_por_nombre(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        // Se cargan desordenados: tienen que salir por nombre. Zulema la tiene; Ana no.
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz']);
        $zulema->disciplinas()->attach($psicologia->id);
        $ana = User::factory()->create(['name' => 'Ana Gómez']);

        $formulario = $this->formulario($psicologia);
        $direccion = auth()->user(); // Quien tiene la sesión también es un usuario: también se puede tildar.

        // La vista recibe a TODOS los usuarios (no solo a los que ya la tienen), por nombre.
        $esperados = collect([$ana, $zulema, $direccion])->sortBy('name')->pluck('name')->all();
        $this->assertSame($esperados, $formulario->viewData('usuarios')->pluck('name')->all());

        // Cada uno con su casilla, conectada a la propiedad "profesionales".
        foreach ([$ana, $zulema, $direccion] as $usuario) {
            $formulario->assertSeeHtml('type="checkbox" wire:model="profesionales" value="'.$usuario->id.'"');
        }

        // Ya no es una lista de solo lectura con links al formulario de cada profesional.
        $formulario->assertDontSee('Todavía nadie.')
            ->assertDontSeeHtml(route('usuarios.editar', $zulema));
    }

    public function test_editar_carga_tildados_a_los_profesionales_que_tienen_la_disciplina(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz']);
        $zulema->disciplinas()->attach($psicologia->id);
        $ana = User::factory()->create(['name' => 'Ana Gómez']);
        $ana->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);
        // Pedro tiene otra disciplina: no tiene que venir tildado.
        User::factory()->create(['name' => 'Pedro Suelto'])->disciplinas()->attach($fonoaudiologia->id);

        // Los ids van como texto, para que coincidan con las casillas. El orden no importa: se comparan ordenados.
        $tildados = $this->formulario($psicologia)->get('profesionales');
        sort($tildados);

        $this->assertSame([(string) $zulema->id, (string) $ana->id], $tildados);
    }

    public function test_al_crear_el_formulario_arranca_sin_nadie_tildado_y_se_puede_elegir_quienes_la_tienen(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Gómez']);
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz']);
        $pedro = User::factory()->create(['name' => 'Pedro Suelto']); // No se tilda.

        $this->formulario()
            ->assertSet('profesionales', [])
            // Las casillas están también al crear, no solo al editar.
            ->assertSee('Quiénes tienen esta disciplina.')
            ->assertSee('Ana Gómez')
            ->set('nombre', 'Psicología')
            ->set('profesionales', [(string) $ana->id, (string) $zulema->id])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios'));

        $disciplina = Disciplina::where('nombre', 'Psicología')->firstOrFail();

        // No son una columna de la disciplina: quedan en la tabla intermedia disciplina_user.
        $this->assertDatabaseCount('disciplina_user', 2);
        $this->assertDatabaseHas('disciplina_user', ['disciplina_id' => $disciplina->id, 'user_id' => $ana->id]);
        $this->assertDatabaseHas('disciplina_user', ['disciplina_id' => $disciplina->id, 'user_id' => $zulema->id]);
        $this->assertDatabaseMissing('disciplina_user', ['user_id' => $pedro->id]);
    }

    public function test_se_puede_crear_una_disciplina_sin_elegir_a_nadie(): void
    {
        User::factory()->create();

        $this->formulario()->set('nombre', 'Psicología')->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseCount('disciplinas', 1);
        $this->assertDatabaseCount('disciplina_user', 0);
    }

    public function test_al_editar_guardar_deja_exactamente_a_los_tildados_sin_tocar_sus_otras_disciplinas(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $ana = User::factory()->create(['name' => 'Ana Gómez']);
        $ana->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);
        $zulema = User::factory()->create(['name' => 'Zulema Ruiz']);
        $zulema->disciplinas()->attach($psicologia->id);
        $pedro = User::factory()->create(['name' => 'Pedro Suelto']);

        $this->formulario($psicologia)
            // Se destilda Ana, queda Zulema y se tilda Pedro.
            ->set('profesionales', [(string) $zulema->id, (string) $pedro->id])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios'));

        $this->assertDatabaseMissing('disciplina_user', ['disciplina_id' => $psicologia->id, 'user_id' => $ana->id]);
        $this->assertDatabaseHas('disciplina_user', ['disciplina_id' => $psicologia->id, 'user_id' => $zulema->id]);
        $this->assertDatabaseHas('disciplina_user', ['disciplina_id' => $psicologia->id, 'user_id' => $pedro->id]);
        // A Ana se le sacó solo Psicología: su otra disciplina sigue.
        $this->assertDatabaseHas('disciplina_user', ['disciplina_id' => $fonoaudiologia->id, 'user_id' => $ana->id]);
        $this->assertDatabaseCount('disciplina_user', 3);
    }

    public function test_destildar_a_todos_al_editar_deja_la_disciplina_sin_profesionales(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        User::factory()->create()->disciplinas()->attach($psicologia->id);
        User::factory()->create()->disciplinas()->attach($psicologia->id);

        $this->formulario($psicologia)
            ->set('profesionales', [])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('disciplina_user', 0);
        // Se les saca el vínculo, pero las personas siguen existiendo (las dos, más quien tiene la sesión).
        $this->assertDatabaseCount('users', 3);
    }

    public function test_guardar_sin_tocar_las_casillas_conserva_a_los_profesionales(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicologia']);
        $ana = User::factory()->create();
        $ana->disciplinas()->attach($psicologia->id);

        // Cambiar solo el nombre no le saca la disciplina a nadie.
        $this->formulario($psicologia)->set('nombre', 'Psicología')->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseCount('disciplina_user', 1);
        $this->assertDatabaseHas('disciplina_user', ['disciplina_id' => $psicologia->id, 'user_id' => $ana->id]);
    }

    public function test_un_profesional_inexistente_da_error_y_no_guarda_nada(): void
    {
        $this->formulario()
            ->set('nombre', 'Psicología')
            ->set('profesionales', ['999']) // No hay ningún usuario con ese id.
            ->call('guardar')
            // El error queda anotado en la casilla que falló (la primera de la lista: posición 0).
            ->assertHasErrors(['profesionales.0' => 'exists'])
            ->assertSeeHtml('Uno de los profesionales elegidos no existe.');

        $this->assertDatabaseCount('disciplinas', 0);
        $this->assertDatabaseCount('disciplina_user', 0);
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

        // Y en la pantalla cada número va en la celda de al lado del nombre de su disciplina.
        // Se mira solo el panel (lo que sigue a su columna "Profesionales"): la tabla de la izquierda también tiene celdas con números.
        $panel = Str::after($lista->html(), 'Profesionales</th>');

        $this->assertMatchesRegularExpression(
            '/Fonoaudiología.*?text-right text-muted">1<\/td>.*?Psicología.*?text-right text-muted">2<\/td>.*?Psicomotricidad.*?text-right text-muted">0<\/td>/su',
            $panel,
        );
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
            // El link para crear va dentro de la tarjeta del panel, al lado del título.
            ->assertSeeInOrder(['Disciplinas', 'href="'.route('disciplinas.crear').'"', '+ Nueva'], false)
            ->html();

        // Ya no hay un link "Editar" aparte, y cada disciplina tiene un solo link.
        $this->assertStringNotContainsString('Editar', $html);
        $this->assertSame(1, substr_count($html, 'href="'.route('disciplinas.editar', $disciplina).'"'));
    }

    public function test_el_panel_es_una_tabla_y_toda_la_fila_lleva_a_editar_la_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);

        $this->lista()
            // Encabezados de la tabla del panel, y la fila con su clic.
            ->assertSeeInOrder([
                '>Disciplina</th>', 'Profesionales</th>',
                'wire:key="disciplina-'.$disciplina->id.'"',
                "window.location = '".route('disciplinas.editar', $disciplina)."'",
            ], false);
    }

    public function test_al_lado_del_titulo_del_panel_va_solo_el_numero_de_disciplinas(): void
    {
        Disciplina::create(['nombre' => 'Psicología']);
        Disciplina::create(['nombre' => 'Fonoaudiología']);

        // Se mira lo que sigue al título del panel (<h2>): la otra etiqueta con número es la de profesionales.
        $panel = Str::after($this->lista()->html(), '<h2');

        $this->assertMatchesRegularExpression('/^[^<]*>\s*Disciplinas\s*<span[^>]*>2<\/span>/u', $panel);
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
