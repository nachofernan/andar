<?php

namespace App\Livewire\Disciplinas;

use App\Livewire\Concerns\SoloDireccion;
use App\Models\Disciplina;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Formulario de disciplina. Sirve para crear (/administracion/disciplinas/crear)
 * y para editar (/administracion/disciplinas/{disciplina}/editar): depende de si $disciplinaId tiene valor.
 * Las disciplinas no se borran. El listado está en la pantalla "Profesionales y disciplinas" (Usuarios\Lista).
 *
 * Vista: resources/views/livewire/disciplinas/formulario.blade.php
 */
class Formulario extends Component
{
    use SoloDireccion; // Solo Dirección puede usar esta pantalla (ver docs/roles.md).

    // Id de la disciplina que se está editando. null = se está creando una nueva.
    public ?int $disciplinaId = null;

    // Conectadas a los campos de la vista con wire:model.
    public string $nombre = '';

    /** Color de la disciplina en hexa (ej. #7c5cf0). Una nueva arranca con el violeta de la app. */
    public string $color = Disciplina::COLOR_INICIAL;

    /** Al abrir la pantalla: si la URL trae una disciplina, carga sus datos en el formulario. */
    public function mount(?Disciplina $disciplina = null): void
    {
        if (! $disciplina?->exists) {
            return; // Se está creando: el formulario queda vacío.
        }

        $this->disciplinaId = $disciplina->id;
        $this->nombre = $disciplina->nombre;
        $this->color = $disciplina->color;
    }

    /** Se ejecuta al enviar el formulario: valida, crea o actualiza la disciplina y vuelve al listado. */
    public function guardar()
    {
        $this->nombre = trim($this->nombre); // Sin espacios de más, para que "Psicología " no cuente como otra.
        $this->color = strtolower(trim($this->color)); // Siempre en minúsculas: #7C5CF0 y #7c5cf0 son el mismo color.

        $datos = $this->validate(
            [
                // Único en la tabla, sin contar la que se está editando (si no, no se podría guardar sin cambiar el nombre).
                'nombre' => ['required', 'max:255', Rule::unique('disciplinas', 'nombre')->ignore($this->disciplinaId)],
                // Hexa completo: numeral y seis caracteres entre 0-9 y a-f.
                'color' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
            ],
            [
                'required' => 'Este campo es obligatorio.',
                'max' => 'El nombre es demasiado largo.',
                'unique' => 'Ya existe una disciplina con ese nombre.',
                'color.regex' => 'Escribí el color en hexa, por ejemplo #7c5cf0.',
            ],
        );

        if ($this->disciplinaId) {
            Disciplina::findOrFail($this->disciplinaId)->update($datos);
        } else {
            Disciplina::create($datos);
        }

        return $this->redirectRoute('usuarios');
    }

    public function render()
    {
        return view('livewire.disciplinas.formulario', [
            // Quiénes tienen esta disciplina, para mostrarlos al editar. Creando una nueva, nadie todavía.
            'profesionales' => $this->disciplinaId
                ? Disciplina::findOrFail($this->disciplinaId)->profesionales()->orderBy('name')->get()
                : collect(),
        ]);
    }
}
