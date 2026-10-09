<?php

namespace App\Livewire;

use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Pantalla de usuarios (los profesionales): lista los que hay y permite crear y editar (no borrar).
 *
 * El mismo formulario sirve para crear y para editar: depende de si $editandoId tiene valor.
 * Vista: resources/views/livewire/usuarios.blade.php
 */
class Usuarios extends Component
{
    /** Contraseña con la que nace todo usuario nuevo. Provisorio, hasta que exista el cambio de contraseña. */
    public const CONTRASENA_INICIAL = 'password';

    // Conectadas a los campos de la vista con wire:model.
    public string $name = '';

    public string $email = '';

    // Texto y no número porque el desplegable manda '' cuando se elige "Sin disciplina".
    public string $disciplina_id = '';

    // Id del usuario que se está editando. null = se está creando uno nuevo.
    public ?int $editandoId = null;

    /** Se ejecuta al enviar el formulario: valida y crea o actualiza el usuario. */
    public function guardar(): void
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);

        $datos = $this->validate(
            [
                'name' => ['required', 'max:255'],
                // Único en la tabla, sin contar al usuario que se está editando.
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editandoId)],
                'disciplina_id' => ['nullable', 'exists:disciplinas,id'],
            ],
            [
                'required' => 'Este campo es obligatorio.',
                'max' => 'El texto es demasiado largo.',
                'email' => 'Ingresá un email válido.',
                'unique' => 'Ya existe un usuario con ese email.',
                'exists' => 'Elegí una disciplina de la lista.',
            ],
        );

        $datos['disciplina_id'] = $datos['disciplina_id'] ?: null; // '' = sin disciplina.

        if ($this->editandoId) {
            // Al editar no se toca la contraseña.
            User::findOrFail($this->editandoId)->update($datos);
        } else {
            User::create($datos + ['password' => self::CONTRASENA_INICIAL]); // Se encripta sola (ver User::casts).
        }

        $this->cancelar();
    }

    /** Carga un usuario en el formulario para editarlo. */
    public function editar(int $id): void
    {
        $usuario = User::findOrFail($id);

        $this->resetValidation();
        $this->editandoId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->disciplina_id = (string) $usuario->disciplina_id;
    }

    /** Vacía el formulario y lo deja listo para crear uno nuevo. */
    public function cancelar(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.usuarios', [
            // with('disciplina') trae la disciplina de todos en una sola consulta.
            'usuarios' => User::with('disciplina')->orderBy('name')->get(),
            'disciplinas' => Disciplina::orderBy('nombre')->get(), // Para el desplegable.
        ]);
    }
}
