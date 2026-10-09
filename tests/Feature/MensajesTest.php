<?php

namespace Tests\Feature;

use App\Livewire\Pacientes\Mensajes;
use App\Models\Disciplina;
use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas del historial de mensajes del equipo, que se muestra dentro de la ficha del paciente.
 * Regla principal: solo el equipo del paciente lo ve y escribe. Los mensajes no se editan ni se borran.
 */
class MensajesTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    private const AVISO = 'Solo el equipo de este paciente puede ver el historial.';

    /** Crea un paciente directo en la base (no hay factory de pacientes), con los datos obligatorios. */
    private function crearPaciente(): Paciente
    {
        return Paciente::create([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
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

    /**
     * Guarda un mensaje directo en la base, sin pasar por el formulario.
     * $fecha permite fijar cuándo se envió (si no se pasa, queda la hora actual).
     */
    private function crearMensaje(Paciente $paciente, User $autor, string $cuerpo, string $tipo = 'mensaje', ?string $fecha = null): Mensaje
    {
        $mensaje = new Mensaje([
            'paciente_id' => $paciente->id,
            'user_id' => $autor->id,
            'tipo' => $tipo,
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

    // ---------------------------------------------------------------- Ficha

    public function test_la_ficha_del_paciente_incluye_el_historial_de_mensajes(): void
    {
        $paciente = $this->crearPaciente();

        // La ficha la abre alguien del equipo (un profesional de afuera recibe 403: ver RolesTest).
        $this->actingAs($this->crearProfesionalDelEquipo($paciente))
            ->get("/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSeeLivewire(Mensajes::class);
    }

    // ---------------------------------------------------------------- Equipo

    public function test_un_profesional_del_equipo_ve_los_mensajes_con_autor_tipo_y_texto(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $marta = $this->crearProfesionalDelEquipo($paciente, 'Marta Díaz');
        $this->crearMensaje($paciente, $marta, 'Terminé la evaluación.', 'informe');

        // La etiqueta del tipo también está en el desplegable y en el filtro, que van más arriba.
        // Por eso se pide el orden: después del nombre de la autora solo puede ser la etiqueta del mensaje.
        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Marta Díaz', 'Informe de evaluación', 'Terminé la evaluación.'])
            ->assertDontSee(self::AVISO)
            ->assertDontSee('Todavía no hay mensajes.');
    }

    public function test_el_mensaje_de_un_profesional_con_dos_disciplinas_las_muestra_en_una_sola_etiqueta(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $lucia->disciplinas()->attach([
            Disciplina::create(['nombre' => 'Psicología'])->id,
            Disciplina::create(['nombre' => 'Fonoaudiología'])->id,
        ]);
        $this->crearMensaje($paciente, $lucia, 'Avanzamos con la lectura.');

        // Una sola etiqueta con las dos, separadas por " · " y en orden alfabético (se cargaron al revés a propósito),
        // entre el nombre de la autora y el texto.
        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Lucía Pérez', 'Fonoaudiología · Psicología', 'Avanzamos con la lectura.']);
    }

    public function test_un_profesional_del_equipo_puede_enviar_un_mensaje(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSet('tipo', 'mensaje') // El tipo por defecto.
            ->set('cuerpo', 'Coordinar reunión con la escuela.')
            ->set('tipo', 'indicacion')
            ->call('enviar')
            ->assertHasNoErrors()
            // El formulario vuelve a quedar como al principio.
            ->assertSet('cuerpo', '')
            ->assertSet('tipo', 'mensaje')
            // Y el mensaje nuevo ya aparece en la lista.
            ->assertSee('Coordinar reunión con la escuela.');

        $this->assertDatabaseCount('mensajes', 1);

        $mensaje = Mensaje::firstOrFail();

        $this->assertSame($paciente->id, $mensaje->paciente_id);
        // El autor es quien tiene la sesión, no un dato que mande el formulario.
        $this->assertSame($lucia->id, $mensaje->user_id);
        $this->assertSame('indicacion', $mensaje->tipo);
        $this->assertSame('Coordinar reunión con la escuela.', $mensaje->cuerpo);
    }

    // ---------------------------------------------------------------- Fuera del equipo

    public function test_un_usuario_que_no_es_del_equipo_ve_el_aviso_y_no_los_mensajes(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->crearMensaje($paciente, $lucia, 'Texto reservado al equipo.');
        $pedro = User::factory()->create(); // Tiene sesión, pero no atiende a este paciente.

        Livewire::actingAs($pedro)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSee(self::AVISO)
            ->assertDontSee('Texto reservado al equipo.')
            ->assertDontSee('Enviar'); // Tampoco se le muestra el formulario.
    }

    public function test_un_usuario_que_no_es_del_equipo_no_puede_enviar_mensajes(): void
    {
        $paciente = $this->crearPaciente();
        $pedro = User::factory()->create();

        // Aunque la vista no le muestre el formulario, el método se podría llamar igual: tiene que rechazarlo.
        Livewire::actingAs($pedro)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'No debería guardarse.')
            ->call('enviar')
            ->assertForbidden();

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_sin_sesion_no_se_ven_ni_se_pueden_enviar_mensajes(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->crearMensaje($paciente, $lucia, 'Texto reservado al equipo.');

        // Sin actingAs: nadie inició sesión.
        Livewire::test(Mensajes::class, ['paciente' => $paciente])
            ->assertSee(self::AVISO)
            ->assertDontSee('Texto reservado al equipo.')
            ->set('cuerpo', 'No debería guardarse.')
            ->call('enviar')
            ->assertForbidden();

        $this->assertDatabaseCount('mensajes', 1); // Solo el que ya estaba.
    }

    // ---------------------------------------------------------------- Validación

    public function test_el_cuerpo_vacio_da_error(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->call('enviar')
            ->assertHasErrors(['cuerpo' => 'required'])
            ->assertSee('Escribí un mensaje.');

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_el_cuerpo_con_solo_espacios_da_error(): void
    {
        $paciente = $this->crearPaciente();

        // Se le quitan los espacios antes de validar, así que queda vacío.
        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', "   \n  ")
            ->call('enviar')
            ->assertHasErrors(['cuerpo' => 'required']);

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_el_cuerpo_de_mas_de_5000_caracteres_da_error(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', str_repeat('a', 5001))
            ->call('enviar')
            ->assertHasErrors(['cuerpo' => 'max']);

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_un_tipo_que_no_esta_en_la_lista_da_error(): void
    {
        $paciente = $this->crearPaciente();

        // El desplegable solo ofrece los tipos válidos, pero el valor se podría cambiar desde el navegador.
        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Texto válido.')
            ->set('tipo', 'inventado')
            ->call('enviar')
            ->assertHasErrors(['tipo' => 'in']);

        $this->assertDatabaseCount('mensajes', 0);
    }

    // ---------------------------------------------------------------- Filtro y orden

    public function test_el_filtro_por_tipo_muestra_solo_los_mensajes_de_ese_tipo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->crearMensaje($paciente, $lucia, 'Un mensaje común.', 'mensaje');
        $this->crearMensaje($paciente, $lucia, 'Un informe largo.', 'informe');
        $this->crearMensaje($paciente, $lucia, 'Una nota de sesión breve.', 'nota');

        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            // Filtro vacío (el de entrada): se ven todos.
            ->assertSet('filtro', '')
            ->assertSee('Un mensaje común.')
            ->assertSee('Un informe largo.')
            ->assertSee('Una nota de sesión breve.')
            // Se elige "Informe de evaluación": quedan solo los informes.
            ->set('filtro', 'informe')
            ->assertSee('Un informe largo.')
            ->assertDontSee('Un mensaje común.')
            ->assertDontSee('Una nota de sesión breve.')
            // Un tipo sin mensajes avisa en vez de quedar en blanco.
            ->set('filtro', 'indicacion')
            ->assertSee('No hay mensajes de este tipo.')
            // Al volver a "Todos" reaparecen.
            ->set('filtro', '')
            ->assertSee('Un mensaje común.')
            ->assertSee('Un informe largo.')
            ->assertSee('Una nota de sesión breve.');
    }

    public function test_los_mensajes_se_muestran_del_mas_nuevo_al_mas_viejo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        // Se cargan desordenados a propósito: el más viejo se guarda último (queda con el id más alto),
        // así se comprueba que el orden sale de la fecha y no del orden de carga.
        $this->crearMensaje($paciente, $lucia, 'Mensaje del medio.', 'mensaje', '2026-10-05 10:00:00');
        $this->crearMensaje($paciente, $lucia, 'Mensaje más nuevo.', 'mensaje', '2026-10-08 09:30:00');
        $this->crearMensaje($paciente, $lucia, 'Mensaje más viejo.', 'mensaje', '2026-10-01 18:00:00');

        Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Mensaje más nuevo.', 'Mensaje del medio.', 'Mensaje más viejo.']);
    }

    // ---------------------------------------------------------------- Modelo

    public function test_el_cuerpo_del_mensaje_se_guarda_cifrado_y_el_modelo_lo_descifra(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $this->crearMensaje($paciente, $lucia, 'Texto confidencial');

        // La fila cruda, sin pasar por el modelo: es lo que vería alguien que abre el archivo de la base.
        $fila = DB::table('mensajes')->first();

        $this->assertNotSame('Texto confidencial', $fila->cuerpo);
        $this->assertStringNotContainsString('Texto confidencial', $fila->cuerpo);

        // Leído por el modelo vuelve el texto original.
        $this->assertSame('Texto confidencial', Mensaje::first()->cuerpo);
    }

    public function test_es_del_equipo_solo_es_verdadero_para_los_profesionales_del_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $pedro = User::factory()->create(); // Existe pero no atiende a este paciente.

        $this->assertTrue($paciente->esDelEquipo($lucia));
        $this->assertFalse($paciente->esDelEquipo($pedro));
        // null = nadie inició sesión.
        $this->assertFalse($paciente->esDelEquipo(null));
    }
}
