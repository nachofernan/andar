<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * "Mi perfil": cada usuario con sesión cambia sus propios datos (nombre, email, teléfono)
 * y, si quiere, su contraseña. La contraseña es opcional: si se deja en blanco, no se toca.
 *
 * Vista: resources/views/livewire/perfil.blade.php
 */
class Perfil extends Component
{
    public string $name = '';

    public string $email = '';

    public string $telefono = '';

    // Cambio de contraseña: los tres campos vacíos significan "no cambiarla".
    public string $contrasena_actual = '';

    public string $contrasena_nueva = '';

    public string $contrasena_nueva_confirmation = '';

    /** Para mostrar el aviso de "Datos actualizados" después de guardar. */
    public bool $guardado = false;

    public function mount(): void
    {
        $usuario = auth()->user();

        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->telefono = $usuario->telefono ?? '';
    }

    public function guardar(): void
    {
        $usuario = auth()->user();
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->telefono = trim($this->telefono);
        $this->guardado = false;

        $this->validate(
            [
                'name' => ['required', 'max:255'],
                // Único en la tabla, sin contar al propio usuario.
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
                'telefono' => ['nullable', 'max:50'],
                // Solo es obligatoria si se quiere poner una contraseña nueva.
                'contrasena_actual' => [Rule::requiredIf(fn () => filled($this->contrasena_nueva))],
                'contrasena_nueva' => ['nullable', 'min:8', 'confirmed'],
            ],
            [
                'required' => 'Este campo es obligatorio.',
                'max' => 'El texto es demasiado largo.',
                'email' => 'Ingresá un email válido.',
                'unique' => 'Ya existe un usuario con ese email.',
                'contrasena_actual.required' => 'Ingresá tu contraseña actual para poder cambiarla.',
                'contrasena_nueva.min' => 'La contraseña nueva tiene que tener al menos 8 caracteres.',
                'contrasena_nueva.confirmed' => 'Las contraseñas no coinciden.',
            ],
        );

        // La contraseña actual se comprueba aparte: no es una regla de formato sino un dato contra la base.
        // Si está mal, no se guarda nada (ni el nombre/email/teléfono), para que el guardado sea todo o nada.
        if (filled($this->contrasena_nueva) && ! Hash::check($this->contrasena_actual, $usuario->password)) {
            $this->addError('contrasena_actual', 'La contraseña actual no es correcta.');

            return;
        }

        $usuario->name = $this->name;
        $usuario->email = $this->email;
        $usuario->telefono = $this->telefono !== '' ? $this->telefono : null;

        if (filled($this->contrasena_nueva)) {
            $usuario->password = $this->contrasena_nueva; // Se encripta sola (ver User::casts).
        }

        $usuario->save();

        $this->reset(['contrasena_actual', 'contrasena_nueva', 'contrasena_nueva_confirmation']);
        $this->guardado = true;
    }

    public function render()
    {
        return view('livewire.perfil');
    }
}
