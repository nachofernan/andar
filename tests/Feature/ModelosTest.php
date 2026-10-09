<?php

namespace Tests\Feature;

use App\Models\Disciplina;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pruebas de los modelos Disciplina y Paciente y de sus relaciones con los profesionales. */
class ModelosTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    /**
     * Crea un paciente con los datos obligatorios. No hay factory de Paciente,
     * así que este atajo evita repetir los mismos campos en cada test.
     *
     * @param  array<string, mixed>  $datos  Campos que se quieran cambiar o agregar.
     */
    private function crearPaciente(array $datos = []): Paciente
    {
        return Paciente::create(array_merge([
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'dni' => '50111222',
            'fecha_nacimiento' => '2018-03-15',
        ], $datos));
    }

    public function test_un_profesional_pertenece_a_una_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Psicología']);
        $profesional = User::factory()->create(['disciplina_id' => $disciplina->id]);

        $this->assertTrue($profesional->disciplina->is($disciplina));
        $this->assertTrue($disciplina->profesionales->contains($profesional));
    }

    public function test_al_borrar_una_disciplina_el_profesional_queda_sin_disciplina(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $profesional = User::factory()->create(['disciplina_id' => $disciplina->id]);

        $disciplina->delete();

        // El profesional no se borra: solo se le vacía la disciplina (nullOnDelete).
        $this->assertNull($profesional->fresh()->disciplina_id);
    }

    public function test_un_paciente_puede_tener_varios_profesionales(): void
    {
        $paciente = $this->crearPaciente();
        $psicologa = User::factory()->create();
        $fonoaudiologa = User::factory()->create();

        $paciente->profesionales()->attach([$psicologa->id, $fonoaudiologa->id]);

        $this->assertCount(2, $paciente->profesionales);
        // La relación también se ve desde el lado del profesional.
        $this->assertTrue($psicologa->pacientes->contains($paciente));
    }

    public function test_no_se_puede_repetir_el_dni_de_un_paciente(): void
    {
        $this->crearPaciente(['dni' => '50111222']);

        $this->expectException(QueryException::class);

        $this->crearPaciente(['nombre' => 'Ana', 'dni' => '50111222']);
    }

    public function test_un_paciente_nuevo_queda_activo_por_defecto(): void
    {
        // Se relee con fresh() porque el valor por defecto lo pone la base, no el modelo.
        $paciente = $this->crearPaciente()->fresh();

        $this->assertTrue($paciente->activo);
    }

    public function test_las_fechas_del_paciente_se_leen_como_fechas(): void
    {
        $paciente = $this->crearPaciente(['fecha_ingreso' => '2026-04-01'])->fresh();

        $this->assertInstanceOf(Carbon::class, $paciente->fecha_nacimiento);
        $this->assertInstanceOf(Carbon::class, $paciente->fecha_ingreso);
        $this->assertSame('2018-03-15', $paciente->fecha_nacimiento->format('Y-m-d'));
        $this->assertSame('2026-04-01', $paciente->fecha_ingreso->format('Y-m-d'));
    }
}
