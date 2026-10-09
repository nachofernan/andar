<?php

namespace App\Livewire\Pacientes;

use App\Models\Paciente;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Formulario de paciente. Sirve para crear (/pacientes/crear) y para editar (/pacientes/{paciente}/editar):
 * depende de si $pacienteId tiene valor.
 *
 * Vista: resources/views/livewire/pacientes/formulario.blade.php
 */
class Formulario extends Component
{
    // Id del paciente que se está editando. null = se está creando uno nuevo.
    public ?int $pacienteId = null;

    // Conectadas a los campos de la vista con wire:model.
    public string $nombre = '';

    public string $apellido = '';

    public string $dni = '';

    public string $fecha_nacimiento = ''; // Formato año-mes-día, el que usa el input de fecha.

    public string $fecha_ingreso = '';

    public string $obra_social = '';

    public string $escuela = '';

    public string $grado = '';

    public string $diagnostico = '';

    public bool $activo = true;

    /** @var array<int, string> Ids de los usuarios tildados como equipo del paciente. */
    public array $equipo = [];

    /** Al abrir la pantalla: si la URL trae un paciente, carga sus datos en el formulario. */
    public function mount(?Paciente $paciente = null): void
    {
        if (! $paciente?->exists) {
            return; // Se está creando: el formulario queda vacío.
        }

        $this->pacienteId = $paciente->id;
        $this->nombre = $paciente->nombre;
        $this->apellido = $paciente->apellido;
        $this->dni = $paciente->dni;
        $this->fecha_nacimiento = $paciente->fecha_nacimiento->format('Y-m-d');
        $this->fecha_ingreso = $paciente->fecha_ingreso?->format('Y-m-d') ?? '';
        $this->obra_social = $paciente->obra_social ?? '';
        $this->escuela = $paciente->escuela ?? '';
        $this->grado = $paciente->grado ?? '';
        $this->diagnostico = $paciente->diagnostico ?? '';
        $this->activo = $paciente->activo;
        // Como texto, porque así los manda el navegador y así se comparan con las casillas.
        $this->equipo = $paciente->profesionales->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Se ejecuta al enviar el formulario: valida, guarda el paciente y su equipo, y va a la ficha. */
    public function guardar()
    {
        // El DNI se guarda solo con números, para que 52.118.304 y 52118304 sean el mismo.
        $this->dni = preg_replace('/\D/', '', $this->dni);

        foreach (['nombre', 'apellido', 'obra_social', 'escuela', 'grado', 'diagnostico'] as $campo) {
            $this->$campo = trim($this->$campo);
        }

        $datos = $this->validate(
            [
                'nombre' => ['required', 'max:255'],
                'apellido' => ['required', 'max:255'],
                // Único en la tabla, sin contar al paciente que se está editando.
                'dni' => ['required', 'max:20', Rule::unique('pacientes', 'dni')->ignore($this->pacienteId)],
                'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
                'fecha_ingreso' => ['nullable', 'date'],
                'obra_social' => ['nullable', 'max:255'],
                'escuela' => ['nullable', 'max:255'],
                'grado' => ['nullable', 'max:255'],
                'diagnostico' => ['nullable', 'max:5000'],
                'activo' => ['boolean'],
                'equipo' => ['array'],
                'equipo.*' => ['exists:users,id'],
            ],
            [
                'required' => 'Este campo es obligatorio.',
                'max' => 'El texto es demasiado largo.',
                'date' => 'Ingresá una fecha válida.',
                'dni.unique' => 'Ya existe un paciente con ese DNI.',
                'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
                'equipo.*.exists' => 'Uno de los profesionales elegidos no existe.',
            ],
        );

        // El equipo no es una columna del paciente: se guarda aparte, en la tabla paciente_user.
        $equipo = $datos['equipo'];
        unset($datos['equipo']);

        // Los opcionales que quedaron vacíos se guardan como null.
        foreach (['fecha_ingreso', 'obra_social', 'escuela', 'grado', 'diagnostico'] as $campo) {
            $datos[$campo] = $datos[$campo] !== '' ? $datos[$campo] : null;
        }

        $paciente = $this->pacienteId ? Paciente::findOrFail($this->pacienteId) : new Paciente;
        $paciente->fill($datos)->save();

        // sync deja como equipo exactamente a los tildados: agrega los nuevos y saca los destildados.
        $paciente->profesionales()->sync($equipo);

        return $this->redirectRoute('pacientes.ver', $paciente);
    }

    public function render()
    {
        return view('livewire.pacientes.formulario', [
            // Todos los usuarios, para las casillas del equipo.
            'usuarios' => User::with('disciplina')->orderBy('name')->get(),
        ]);
    }
}
