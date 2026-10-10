<?php

namespace Tests\Feature;

use App\Livewire\Inicio;
use App\Livewire\Pacientes\Lista;
use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas del Inicio: el saludo con el aviso de mensajes sin leer y la tabla "Mis pacientes"
 * (quién ve qué paciente, el buscador, los filtros, el orden y la columna "Última actividad").
 * El aviso con la cantidad de mensajes sin leer de cada paciente se prueba en MensajesSinLeerTest.
 *
 * Después de una acción (call o set) el orden se comprueba con assertSeeHtmlInOrder y no con assertSeeInOrder:
 * este último mira la respuesta cruda de Livewire, que tras una acción viene como JSON con las tildes
 * escritas en código ("Álvarez"), y entonces no encuentra los nombres con tilde.
 */
class InicioTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    private const SIN_PACIENTES = 'Todavía no tenés pacientes asignados.';

    private const SIN_COINCIDENCIAS = 'Ningún paciente coincide con la búsqueda o los filtros.';

    /** Crea un paciente directo en la base (no hay factory de pacientes). Cada uno necesita un DNI distinto. */
    private function crearPaciente(array $datos = []): Paciente
    {
        return Paciente::create(array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
        ], $datos))->refresh();
    }

    /**
     * Crea un paciente a partir de "Apellido, Nombre" y lo suma al equipo de esos profesionales.
     * El DNI se arma solo (cada paciente necesita uno distinto).
     */
    private function crearPacienteDe(array $profesionales, string $apellido, string $nombre, array $datos = []): Paciente
    {
        $paciente = $this->crearPaciente(array_merge([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => (string) (40000000 + Paciente::count()),
        ], $datos));

        $paciente->profesionales()->attach(collect($profesionales)->pluck('id'));

        return $paciente;
    }

    /**
     * Guarda un mensaje directo en la base, sin pasar por el formulario.
     * $fecha permite fijar cuándo se envió (si no se pasa, queda la hora actual).
     */
    private function crearMensaje(Paciente $paciente, User $autor, string $cuerpo = 'Un mensaje.', ?string $fecha = null): Mensaje
    {
        $mensaje = new Mensaje([
            'paciente_id' => $paciente->id,
            'user_id' => $autor->id,
            'tipo' => 'mensaje',
            'cuerpo' => $cuerpo,
        ]);

        // Las fechas no se pueden pasar en el array de arriba: se asignan aparte.
        if ($fecha !== null) {
            $mensaje->created_at = $fecha;
            $mensaje->updated_at = $fecha;
        }

        $mensaje->save();

        return $mensaje;
    }

    // ---------------------------------------------------------------- Quién ve qué paciente

    public function test_un_profesional_ve_solo_los_pacientes_de_su_equipo(): void
    {
        $ana = $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304']);
        $bruno = $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222']);
        $this->crearPaciente(['nombre' => 'Carla', 'apellido' => 'Molina', 'dni' => '45333444']); // Sin equipo.
        $lucia = User::factory()->create();
        $ana->profesionales()->attach($lucia->id);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno')
            ->assertDontSee('Molina, Carla');

        // A Bruno lo deja afuera también si lo atiende otra persona.
        $bruno->profesionales()->attach(User::factory()->create()->id);
        Livewire::actingAs($lucia)->test(Inicio::class)->assertDontSee('Arnal, Bruno');
    }

    public function test_direccion_tambien_ve_solo_los_pacientes_que_atiende(): void
    {
        $ana = $this->crearPaciente(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '52118304']);
        $this->crearPaciente(['nombre' => 'Bruno', 'apellido' => 'Arnal', 'dni' => '40111222']); // No la atiende.
        $direccion = User::factory()->direccion()->create();
        $ana->profesionales()->attach($direccion->id);

        Livewire::actingAs($direccion)
            ->test(Inicio::class)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno');
    }

    public function test_sin_pacientes_asignados_avisa_que_no_tiene(): void
    {
        $this->crearPaciente(); // Hay un paciente cargado, pero no es suyo.

        Livewire::actingAs(User::factory()->create())
            ->test(Inicio::class)
            ->assertSee(self::SIN_PACIENTES)
            ->assertDontSee(self::SIN_COINCIDENCIAS)
            ->assertDontSee('Zapata, Ana')
            // Aunque se toquen los filtros el aviso es el mismo: el problema no son los filtros, es que no tiene ninguno.
            ->set('buscar', 'zapata')
            ->assertSee(self::SIN_PACIENTES)
            ->assertDontSee(self::SIN_COINCIDENCIAS);
    }

    // ---------------------------------------------------------------- Filtro de estado

    public function test_por_defecto_no_muestra_los_pacientes_dados_de_baja(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana', ['activo' => true]);
        $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno', ['activo' => false]);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSet('estado', 'activos')
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno');
    }

    public function test_el_filtro_de_estado_en_todos_suma_los_inactivos_a_la_lista(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana', ['activo' => true]);
        $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno', ['activo' => false]);

        // Reemplaza al viejo toggle "ver inactivos".
        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('estado', 'todos')
            ->assertSee('Zapata, Ana')
            ->assertSee('Arnal, Bruno')
            // Con mayúscula y entre etiquetas: es la etiqueta de la fila, no la opción "Inactivos" del desplegable.
            ->assertSeeHtml('>Inactivo</span>');
    }

    public function test_el_filtro_de_estado_en_inactivos_muestra_solo_los_dados_de_baja(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana', ['activo' => true]);
        $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno', ['activo' => false]);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('estado', 'inactivos')
            ->assertSee('Arnal, Bruno')
            ->assertDontSee('Zapata, Ana')
            // Y se puede volver a los activos.
            ->set('estado', 'activos')
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno');
    }

    public function test_si_ningun_paciente_pasa_el_filtro_avisa_que_ninguno_coincide(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana', ['activo' => true]);

        // Tiene pacientes, pero ninguno inactivo: no es lo mismo que no tener ninguno asignado.
        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('estado', 'inactivos')
            ->assertSee(self::SIN_COINCIDENCIAS)
            ->assertDontSee(self::SIN_PACIENTES)
            ->assertDontSee('Zapata, Ana');
    }

    // ---------------------------------------------------------------- Buscador

    public function test_el_buscador_encuentra_por_nombre_o_apellido_sin_importar_tildes_ni_mayusculas(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Gómez', 'Íñigo');
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');

        $inicio = Livewire::actingAs($lucia)->test(Inicio::class)->assertSet('buscar', '');

        // Sin tilde y en minúscula encuentra a "Gómez".
        $inicio->set('buscar', 'gomez')->assertSee('Gómez, Íñigo')->assertDontSee('Zapata, Ana');
        // En mayúsculas, y por el nombre en vez del apellido.
        $inicio->set('buscar', 'INIGO')->assertSee('Gómez, Íñigo')->assertDontSee('Zapata, Ana');
        // Escrito con tilde encuentra igual.
        $inicio->set('buscar', 'Gómez')->assertSee('Gómez, Íñigo')->assertDontSee('Zapata, Ana');
        // Los espacios de más al principio o al final no molestan.
        $inicio->set('buscar', '  zapata ')->assertSee('Zapata, Ana')->assertDontSee('Gómez, Íñigo');
        // Con el buscador vacío vuelven todos.
        $inicio->set('buscar', '')->assertSee('Gómez, Íñigo')->assertSee('Zapata, Ana');
    }

    public function test_el_buscador_acepta_el_nombre_escrito_de_las_dos_formas(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Gómez', 'Íñigo');
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');

        $inicio = Livewire::actingAs($lucia)->test(Inicio::class);

        // "Nombre Apellido", como se dice.
        $inicio->set('buscar', 'ana zapata')->assertSee('Zapata, Ana')->assertDontSee('Gómez, Íñigo');
        // "Apellido, Nombre", como se ve en la tabla.
        $inicio->set('buscar', 'zapata, ana')->assertSee('Zapata, Ana')->assertDontSee('Gómez, Íñigo');
    }

    public function test_si_la_busqueda_no_encuentra_nada_avisa_que_ninguno_coincide(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('buscar', 'nadie-se-llama-asi')
            ->assertSee(self::SIN_COINCIDENCIAS)
            ->assertDontSee(self::SIN_PACIENTES)
            ->assertDontSee('Zapata, Ana');
    }

    public function test_el_buscador_no_encuentra_pacientes_de_otros_equipos(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');
        $this->crearPacienteDe([User::factory()->create()], 'Arnal', 'Bruno'); // Lo atiende otra persona.

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('buscar', 'arnal')
            ->set('estado', 'todos')
            ->assertDontSee('Arnal, Bruno')
            ->assertSee(self::SIN_COINCIDENCIAS);
    }

    // ---------------------------------------------------------------- Orden

    public function test_la_tabla_abre_ordenada_por_paciente_y_un_clic_en_la_columna_invierte_el_sentido(): void
    {
        $lucia = User::factory()->create();
        // Se cargan desordenados a propósito.
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');
        $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno');
        // "Álvarez" lleva tilde: tiene que ordenarse en la A, antes que "Arnal", y no después de la Z.
        $this->crearPacienteDe([$lucia], 'Álvarez', 'Dana');
        $this->crearPacienteDe([$lucia], 'Molina', 'Carla');

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSet('orden', 'paciente')
            ->assertSet('ascendente', true)
            ->assertSeeInOrder(['Álvarez, Dana', 'Arnal, Bruno', 'Molina, Carla', 'Zapata, Ana'])
            ->call('ordenarPor', 'paciente')
            ->assertSet('orden', 'paciente')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Molina, Carla', 'Arnal, Bruno', 'Álvarez, Dana'])
            // Un tercer clic vuelve al sentido original.
            ->call('ordenarPor', 'paciente')
            ->assertSet('ascendente', true)
            ->assertSeeHtmlInOrder(['Álvarez, Dana', 'Arnal, Bruno', 'Molina, Carla', 'Zapata, Ana']);
    }

    public function test_la_tabla_se_puede_ordenar_por_edad(): void
    {
        $lucia = User::factory()->create();
        // Las edades van en otro orden que los apellidos, para distinguir por cuál columna se ordenó.
        $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno', ['fecha_nacimiento' => '2015-06-01']);
        $this->crearPacienteDe([$lucia], 'Molina', 'Carla', ['fecha_nacimiento' => '2020-06-01']); // La más chica.
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana', ['fecha_nacimiento' => '2012-06-01']); // La más grande.

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->call('ordenarPor', 'edad')
            ->assertSet('orden', 'edad')
            // Una columna nueva arranca de menor a mayor: primero la más chica.
            ->assertSet('ascendente', true)
            ->assertSeeHtmlInOrder(['Molina, Carla', 'Arnal, Bruno', 'Zapata, Ana'])
            ->call('ordenarPor', 'edad')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Arnal, Bruno', 'Molina, Carla']);
    }

    public function test_la_tabla_se_puede_ordenar_por_ultima_actividad(): void
    {
        $lucia = User::factory()->create();
        $bruno = $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno');
        $this->crearPacienteDe([$lucia], 'Molina', 'Carla'); // Sin mensajes.
        $ana = $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');
        // Los escribe la propia Lucía: así no quedan "sin leer" y no se mezcla esa regla con el orden.
        $this->crearMensaje($ana, $lucia, 'Primero.', '2026-10-01 10:00:00');
        $this->crearMensaje($bruno, $lucia, 'Segundo.', '2026-10-02 10:00:00'); // El más reciente.

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->call('ordenarPor', 'actividad')
            ->assertSet('orden', 'actividad')
            ->assertSet('ascendente', true)
            // De la actividad más vieja a la más nueva; quien no tiene mensajes va antes que todos.
            ->assertSeeHtmlInOrder(['Molina, Carla', 'Zapata, Ana', 'Arnal, Bruno'])
            ->call('ordenarPor', 'actividad')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Arnal, Bruno', 'Zapata, Ana', 'Molina, Carla']);
    }

    public function test_cambiar_de_columna_arranca_de_nuevo_en_ascendente(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->call('ordenarPor', 'paciente') // Queda por paciente, descendente.
            ->assertSet('ascendente', false)
            ->call('ordenarPor', 'edad')
            ->assertSet('orden', 'edad')
            ->assertSet('ascendente', true);
    }

    public function test_ordenar_por_una_columna_que_no_existe_no_cambia_nada(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno', ['fecha_nacimiento' => '2015-06-01']);
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana', ['fecha_nacimiento' => '2020-06-01']);

        // El nombre de la columna viaja desde el navegador: se podría mandar cualquier cosa.
        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->call('ordenarPor', 'edad')
            ->call('ordenarPor', 'edad') // Queda por edad, descendente: primero Bruno, el más grande.
            ->call('ordenarPor', 'diagnostico')
            ->assertSet('orden', 'edad')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Arnal, Bruno', 'Zapata, Ana'])
            // "estado" es columna en el listado de administración, pero en el Inicio no se ordena por ella.
            ->call('ordenarPor', 'estado')
            ->assertSet('orden', 'edad')
            ->assertSet('ascendente', false);
    }

    // ---------------------------------------------------------------- Mensajes sin leer: siempre arriba

    public function test_los_pacientes_con_mensajes_sin_leer_van_arriba_con_cualquier_orden(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        // Las que tienen mensajes sin leer son, a propósito, las últimas por apellido y las más grandes:
        // con ningún orden quedarían arriba por casualidad.
        $this->crearPacienteDe([$lucia, $marta], 'Álvarez', 'Dana', ['fecha_nacimiento' => '2018-06-01']);
        $this->crearPacienteDe([$lucia, $marta], 'Arnal', 'Bruno', ['fecha_nacimiento' => '2020-06-01']);
        $carla = $this->crearPacienteDe([$lucia, $marta], 'Molina', 'Carla', ['fecha_nacimiento' => '2012-06-01']);
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana', ['fecha_nacimiento' => '2014-06-01']);
        // Con fechas distintas, porque "última actividad" ordena por la fecha del último mensaje: el de Ana es el más nuevo.
        $this->crearMensaje($carla, $marta, fecha: '2026-10-01 10:00:00');
        $this->crearMensaje($ana, $marta, fecha: '2026-10-02 10:00:00');
        $this->crearMensaje($ana, $marta, fecha: '2026-10-03 10:00:00');

        $inicio = Livewire::actingAs($lucia)->test(Inicio::class);

        // Por paciente (como abre): primero las dos con sin leer, por apellido; después el resto, por apellido.
        $inicio->assertSeeInOrder(['Molina, Carla', 'Zapata, Ana', 'Álvarez, Dana', 'Arnal, Bruno']);

        // Invertir el sentido da vuelta cada grupo, pero no los mezcla.
        $inicio->call('ordenarPor', 'paciente')
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Molina, Carla', 'Arnal, Bruno', 'Álvarez, Dana']);

        // Por edad, de menor a mayor: Ana (2014) y Carla (2012) siguen arriba aunque sean las más grandes.
        $inicio->call('ordenarPor', 'edad')
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Molina, Carla', 'Arnal, Bruno', 'Álvarez, Dana']);

        // Por edad, de mayor a menor.
        $inicio->call('ordenarPor', 'edad')
            ->assertSeeHtmlInOrder(['Molina, Carla', 'Zapata, Ana', 'Álvarez, Dana', 'Arnal, Bruno']);

        // Por última actividad, de la más nueva a la más vieja: sin la regla, las que no tienen mensajes irían al final
        // igual; por eso se prueba también de la más vieja a la más nueva, donde irían primero.
        $inicio->call('ordenarPor', 'actividad')
            ->assertSet('ascendente', true)
            ->assertSeeHtmlInOrder(['Molina, Carla', 'Zapata, Ana', 'Arnal, Bruno'])
            ->assertSeeHtmlInOrder(['Molina, Carla', 'Zapata, Ana', 'Álvarez, Dana']);

        $inicio->call('ordenarPor', 'actividad')
            ->assertSet('ascendente', false)
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Molina, Carla', 'Arnal, Bruno'])
            ->assertSeeHtmlInOrder(['Zapata, Ana', 'Molina, Carla', 'Álvarez, Dana']);
    }

    public function test_un_mensaje_propio_no_sube_al_paciente_arriba(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        $bruno = $this->crearPacienteDe([$lucia, $marta], 'Arnal', 'Bruno');
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $this->crearMensaje($ana, $lucia); // Lo escribió ella misma: no está "sin leer".

        // Sin mensajes sin leer, manda el apellido.
        Livewire::actingAs($lucia)->test(Inicio::class)->assertSeeInOrder(['Arnal, Bruno', 'Zapata, Ana']);

        // En cambio a Marta, que no lo leyó, Ana le sube arriba.
        Livewire::actingAs($marta)->test(Inicio::class)->assertSeeInOrder(['Zapata, Ana', 'Arnal, Bruno']);
    }

    public function test_el_filtro_solo_sin_leer_deja_solo_los_pacientes_con_mensajes_sin_leer(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        $this->crearPacienteDe([$lucia, $marta], 'Arnal', 'Bruno'); // Sin mensajes.
        $carla = $this->crearPacienteDe([$lucia, $marta], 'Molina', 'Carla');
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($carla, $lucia); // Tiene un mensaje, pero propio: no está sin leer.

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSet('soloSinLeer', false)
            ->assertSee('Arnal, Bruno')
            ->assertSee('Molina, Carla')
            ->assertSee('Zapata, Ana')
            ->set('soloSinLeer', true)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Arnal, Bruno')
            ->assertDontSee('Molina, Carla')
            // Al destildarlo vuelven todos.
            ->set('soloSinLeer', false)
            ->assertSee('Arnal, Bruno')
            ->assertSee('Molina, Carla');
    }

    public function test_el_filtro_solo_sin_leer_sin_nada_pendiente_avisa_que_ninguno_coincide(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('soloSinLeer', true)
            ->assertSee(self::SIN_COINCIDENCIAS)
            ->assertDontSee(self::SIN_PACIENTES)
            ->assertDontSee('Zapata, Ana');
    }

    public function test_el_buscador_y_los_filtros_se_combinan(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $bruno = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Bruno');
        $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Carla'); // Sin mensajes.
        $dana = $this->crearPacienteDe([$lucia, $marta], 'Molina', 'Dana');
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);
        $this->crearMensaje($dana, $marta);
        $bruno->update(['activo' => false]);

        // Apellido Zapata + con mensajes sin leer + activo: solo Ana cumple las tres.
        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('buscar', 'zapata')
            ->set('soloSinLeer', true)
            ->assertSee('Zapata, Ana')
            ->assertDontSee('Zapata, Bruno') // Inactivo (y por eso su mensaje tampoco cuenta como sin leer).
            ->assertDontSee('Zapata, Carla') // Sin mensajes sin leer.
            ->assertDontSee('Molina, Dana'); // Otro apellido.
    }

    // ---------------------------------------------------------------- Se recuerda durante la sesión

    public function test_el_buscador_los_filtros_y_el_orden_se_recuerdan_al_volver_al_inicio(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        // Bruno está activo: los mensajes de un paciente inactivo no cuentan como sin leer,
        // así que con "solo sin leer" tildado un inactivo nunca aparecería.
        $bruno = $this->crearPacienteDe([$lucia, $marta], 'Arnal', 'Bruno');
        $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $this->crearMensaje($bruno, $marta);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('buscar', 'arnal')
            ->set('estado', 'todos')
            ->set('soloSinLeer', true)
            ->call('ordenarPor', 'actividad')
            ->call('ordenarPor', 'actividad'); // Queda por última actividad, descendente.

        // Se abre de nuevo en la misma sesión (como al entrar a la ficha de un paciente y volver):
        // la tabla sigue como se la dejó, no vuelve a los valores iniciales.
        Livewire::test(Inicio::class)
            ->assertSet('buscar', 'arnal')
            ->assertSet('estado', 'todos')
            ->assertSet('soloSinLeer', true)
            ->assertSet('orden', 'actividad')
            ->assertSet('ascendente', false)
            // Y muestra lo que corresponde a esos filtros: Bruno coincide con la búsqueda y tiene un mensaje sin leer.
            ->assertSee('Arnal, Bruno')
            ->assertDontSee('Zapata, Ana');
    }

    public function test_en_una_sesion_nueva_el_inicio_abre_con_los_valores_iniciales(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('buscar', 'arnal')
            ->set('estado', 'todos')
            ->set('soloSinLeer', true)
            ->call('ordenarPor', 'edad');

        // Lo recordado vive en la sesión: al cerrarla (salir y volver a entrar) no queda nada.
        session()->flush();

        Livewire::test(Inicio::class)
            ->assertSet('buscar', '')
            ->assertSet('estado', 'activos')
            ->assertSet('soloSinLeer', false)
            ->assertSet('orden', 'paciente')
            ->assertSet('ascendente', true)
            ->assertSee('Zapata, Ana');
    }

    public function test_lo_que_se_recuerda_del_inicio_no_se_mezcla_con_el_listado_de_administracion(): void
    {
        // Las dos pantallas tienen buscador, filtro de estado y orden con los mismos nombres:
        // cada una tiene que recordar los suyos.
        $direccion = User::factory()->direccion()->create();
        $this->crearPacienteDe([$direccion], 'Zapata', 'Ana');

        Livewire::actingAs($direccion)
            ->test(Inicio::class)
            ->set('buscar', 'zapata')
            ->set('estado', 'inactivos')
            ->call('ordenarPor', 'edad');

        Livewire::test(Lista::class)
            ->assertSet('buscar', '')
            ->assertSet('estado', 'activos')
            ->assertSet('orden', 'paciente')
            ->assertSee('Zapata, Ana');
    }

    // ---------------------------------------------------------------- Saludo

    public function test_el_saludo_nombra_al_usuario_y_dice_cuantos_mensajes_sin_leer_tiene_y_de_cuantos_pacientes(): void
    {
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $marta = User::factory()->create(['name' => 'Marta Díaz']);
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $bruno = $this->crearPacienteDe([$lucia, $marta], 'Arnal', 'Bruno');
        $this->crearPacienteDe([$lucia, $marta], 'Molina', 'Carla'); // Sin mensajes.
        $ajeno = $this->crearPacienteDe([$marta], 'Ruiz', 'Emma'); // No es de Lucía: no cuenta en nada.
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($ana, $marta);
        $this->crearMensaje($bruno, $marta);
        $this->crearMensaje($bruno, $lucia); // Propio: no está sin leer.
        $this->crearMensaje($ajeno, $marta);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            // El saludo va arriba de la tabla.
            ->assertSeeInOrder(['Hola, Lucía Pérez', 'Tenés', '>3 mensajes sin leer</span>', 'de 2 pacientes.', 'Mis pacientes'], false)
            ->assertDontSee('No tenés mensajes nuevos.');
    }

    public function test_el_saludo_va_en_singular_cuando_es_un_mensaje_de_un_paciente(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $this->crearMensaje($ana, $marta);

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSeeInOrder(['Tenés', '>1 mensaje sin leer</span>', 'de 1 paciente.'], false);
    }

    public function test_el_saludo_avisa_cuando_no_hay_mensajes_nuevos(): void
    {
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $ana = $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');
        $this->crearMensaje($ana, $lucia); // Propio: no cuenta.

        $html = Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSeeInOrder(['Hola, Lucía Pérez', 'No tenés mensajes nuevos.'])
            ->html();

        // Ni un "Tenés 0 mensajes sin leer".
        $this->assertStringNotContainsString('sin leer</span>', $html);
    }

    public function test_el_saludo_no_cambia_con_el_buscador_ni_los_filtros(): void
    {
        $lucia = User::factory()->create();
        $marta = User::factory()->create();
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $this->crearPacienteDe([$lucia, $marta], 'Arnal', 'Bruno');
        $this->crearMensaje($ana, $marta);

        // La tabla queda vacía, pero el saludo sigue contando sobre todos mis pacientes.
        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->set('buscar', 'nadie-se-llama-asi')
            ->assertSeeHtml(self::SIN_COINCIDENCIAS)
            ->assertSeeHtml('>1 mensaje sin leer</span>')
            ->set('buscar', '')
            ->set('estado', 'inactivos')
            ->assertSeeHtml('>1 mensaje sin leer</span>');
    }

    public function test_ya_no_hay_tarjetas_de_resumen(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');

        // Se sacaron las tres tarjetas y el dato "resumen" que las alimentaba.
        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertViewMissing('resumen')
            ->assertDontSee('Pacientes activos')
            ->assertDontSee('Pacientes con mensajes sin leer');
    }

    public function test_al_lado_del_titulo_va_solo_el_numero_de_pacientes_que_hay_en_la_tabla(): void
    {
        $lucia = User::factory()->create();
        $this->crearPacienteDe([$lucia], 'Zapata', 'Ana');
        $this->crearPacienteDe([$lucia], 'Arnal', 'Bruno');
        $this->crearPacienteDe([$lucia], 'Álvarez', 'Dana', ['activo' => false]);

        // Así sale la etiqueta: el número solo, sin la palabra "pacientes".
        $etiqueta = fn (int $cantidad) => 'text-brand">'.$cantidad.'</span>';

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            // Cuenta lo que se ve: abre con los dos activos.
            ->assertSeeInOrder(['Mis pacientes', $etiqueta(2)], false)
            // Y cambia con los filtros y el buscador.
            ->set('estado', 'todos')->assertSeeHtml($etiqueta(3))
            ->set('buscar', 'zapata')->assertSeeHtml($etiqueta(1))
            ->set('buscar', 'nadie-se-llama-asi')->assertSeeHtml($etiqueta(0));
    }

    // ---------------------------------------------------------------- Columna "Última actividad"

    public function test_la_ultima_actividad_muestra_la_fecha_y_el_autor_del_ultimo_mensaje_pero_nunca_el_texto(): void
    {
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $marta = User::factory()->create(['name' => 'Marta Díaz']);
        $pedro = User::factory()->create(['name' => 'Pedro Ruiz']);
        $ana = $this->crearPacienteDe([$lucia, $marta, $pedro], 'Zapata', 'Ana');
        $this->crearMensaje($ana, $marta, 'Primer texto reservado.', '2026-10-01 09:00:00');
        $this->crearMensaje($ana, $pedro, 'Segundo texto reservado.', '2026-10-03 18:30:00'); // El último.

        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSee('03/10/2026 · Pedro Ruiz')
            // Solo el último: ni la fecha ni la autora del anterior.
            ->assertDontSee('01/10/2026')
            ->assertDontSee('Marta Díaz')
            // El texto de los mensajes es dato sensible: en el Inicio no aparece nunca.
            ->assertDontSee('Primer texto reservado.')
            ->assertDontSee('Segundo texto reservado.')
            ->assertDontSee('reservado');
    }

    public function test_la_ultima_actividad_tambien_cuenta_los_mensajes_propios(): void
    {
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $marta = User::factory()->create(['name' => 'Marta Díaz']);
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $this->crearMensaje($ana, $marta, 'De Marta.', '2026-10-01 09:00:00');
        $this->crearMensaje($ana, $lucia, 'De Lucía.', '2026-10-02 09:00:00');

        // Es la última actividad del paciente, la haya escrito quien la haya escrito.
        Livewire::actingAs($lucia)
            ->test(Inicio::class)
            ->assertSee('02/10/2026 · Lucía Pérez')
            ->assertDontSee('01/10/2026');
    }

    public function test_la_ultima_actividad_de_cada_paciente_es_la_suya_y_sin_mensajes_muestra_una_raya(): void
    {
        $lucia = User::factory()->create(['name' => 'Lucía Pérez']);
        $marta = User::factory()->create(['name' => 'Marta Díaz']);
        $bruno = $this->crearPacienteDe([$lucia, $marta], 'Arnal', 'Bruno');
        $this->crearPacienteDe([$lucia, $marta], 'Molina', 'Carla'); // Sin mensajes.
        $ana = $this->crearPacienteDe([$lucia, $marta], 'Zapata', 'Ana');
        $this->crearMensaje($bruno, $lucia, 'Sobre Bruno.', '2026-09-15 09:00:00');
        $this->crearMensaje($ana, $lucia, 'Sobre Ana.', '2026-10-02 09:00:00');

        $html = Livewire::actingAs($lucia)
            ->test(Inicio::class)
            // Cada fecha en el renglón de su paciente; Carla, que no tiene mensajes, lleva la raya.
            ->assertSeeInOrder([
                'Arnal, Bruno', '15/09/2026 · Lucía Pérez',
                'Molina, Carla', '—',
                'Zapata, Ana', '02/10/2026 · Lucía Pérez',
            ])
            ->html();

        // Una sola raya en toda la pantalla: la de Carla.
        $this->assertSame(1, substr_count($html, '—'));
    }
}
