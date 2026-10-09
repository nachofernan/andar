<?php

namespace App\Livewire;

use App\Models\Disciplina;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Pantalla de disciplinas: lista las que hay y permite crear y editar (no borrar).
 *
 * El mismo formulario sirve para crear y para editar: depende de si $editandoId tiene valor.
 * Vista: resources/views/livewire/disciplinas.blade.php
 */
class Disciplinas extends Component
{
    // Conectada al input de la vista con wire:model.
    public string $nombre = '';

    // Id de la disciplina que se está editando. null = se está creando una nueva.
    public ?int $editandoId = null;

    /** Se ejecuta al enviar el formulario: valida y crea o actualiza la disciplina. */
    public function guardar(): void
    {
        $this->nombre = trim($this->nombre); // Sin espacios de más, para que "Psicología " no cuente como otra.

        $datos = $this->validate(
            [
                // Único en la tabla, sin contar la que se está editando (si no, no se podría guardar sin cambiar el nombre).
                'nombre' => ['required', 'max:255', Rule::unique('disciplinas', 'nombre')->ignore($this->editandoId)],
            ],
            [
                'required' => 'Este campo es obligatorio.',
                'max' => 'El nombre es demasiado largo.',
                'unique' => 'Ya existe una disciplina con ese nombre.',
            ],
        );

        if ($this->editandoId) {
            Disciplina::findOrFail($this->editandoId)->update($datos);
        } else {
            Disciplina::create($datos);
        }

        $this->cancelar();
    }

    /** Carga una disciplina en el formulario para editarla. */
    public function editar(int $id): void
    {
        $disciplina = Disciplina::findOrFail($id);

        $this->resetValidation();
        $this->editandoId = $disciplina->id;
        $this->nombre = $disciplina->nombre;
    }

    /** Vacía el formulario y lo deja listo para crear una nueva. */
    public function cancelar(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.disciplinas', [
            // withCount agrega profesionales_count: cuántos profesionales tiene cada disciplina.
            'disciplinas' => Disciplina::withCount('profesionales')->orderBy('nombre')->get(),
        ]);
    }
}
