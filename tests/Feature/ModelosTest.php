<?php

namespace Tests\Feature;

use App\Models\Disciplina;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_un_profesional_puede_tener_varias_disciplinas(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $profesional = User::factory()->create();

        $profesional->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);

        // Salen por orden alfabético, no por el orden en que se cargaron.
        $this->assertSame(['Fonoaudiología', 'Psicología'], $profesional->disciplinas->pluck('nombre')->all());
        // La relación también se ve desde el lado de cada disciplina.
        $this->assertTrue($psicologia->profesionales->contains($profesional));
        $this->assertTrue($fonoaudiologia->profesionales->contains($profesional));
    }

    public function test_al_borrar_una_disciplina_al_profesional_se_le_quita_esa_y_conserva_las_otras(): void
    {
        $psicologia = Disciplina::create(['nombre' => 'Psicología']);
        $fonoaudiologia = Disciplina::create(['nombre' => 'Fonoaudiología']);
        $profesional = User::factory()->create();
        $profesional->disciplinas()->attach([$psicologia->id, $fonoaudiologia->id]);

        $fonoaudiologia->delete();

        // El profesional no se borra: solo se borra el vínculo con esa disciplina (cascadeOnDelete).
        $this->assertNotNull($profesional->fresh());
        $this->assertSame(['Psicología'], $profesional->fresh()->disciplinas->pluck('nombre')->all());
        $this->assertDatabaseCount('disciplina_user', 1);
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

    public function test_el_dni_y_el_diagnostico_se_guardan_cifrados_y_el_modelo_los_descifra(): void
    {
        $this->crearPaciente(['dni' => '50111222', 'diagnostico' => 'Retraso del lenguaje']);

        // La fila cruda, sin pasar por el modelo: es lo que vería alguien que abre el archivo de la base.
        $fila = DB::table('pacientes')->first();

        $this->assertNotSame('50111222', $fila->dni);
        $this->assertStringNotContainsString('50111222', $fila->dni);
        $this->assertNotSame('Retraso del lenguaje', $fila->diagnostico);
        $this->assertStringNotContainsString('Retraso del lenguaje', $fila->diagnostico);

        // Leído por el modelo vuelve el texto original.
        $paciente = Paciente::first();

        $this->assertSame('50111222', $paciente->dni);
        $this->assertSame('Retraso del lenguaje', $paciente->diagnostico);
    }

    public function test_un_diagnostico_vacio_queda_en_null_y_no_se_cifra(): void
    {
        $this->crearPaciente(); // Sin diagnóstico.

        // El cifrado no tiene que convertir el null en un texto cifrado.
        $this->assertNull(DB::table('pacientes')->first()->diagnostico);
        $this->assertNull(Paciente::first()->diagnostico);
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
