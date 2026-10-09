<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Pantalla de inicio de sesión (email + contraseña).
 *
 * No hay registro público: los usuarios se crean a mano o por seeder.
 * Vista: resources/views/livewire/login.blade.php
 */
class Login extends Component
{
    // Estas propiedades están conectadas a los inputs de la vista con wire:model.
    public string $email = 'catalina@andar.test'; // Para que no haya que escribirlo cada vez al probar.

    public string $password = 'password'; // Para que no haya que escribirlo cada vez al probar.

    public bool $recordarme = false;

    /** Se ejecuta al enviar el formulario: valida y trata de iniciar sesión. */
    public function entrar()
    {
        $this->validate(
            ['email' => 'required|email', 'password' => 'required'],
            ['required' => 'Este campo es obligatorio.', 'email' => 'Ingresá un email válido.'],
        );

        // Auth::attempt compara la contraseña con el hash guardado en la base.
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->recordarme)) {
            $this->addError('email', 'Email o contraseña incorrectos.');

            return;
        }

        session()->regenerate(); // Renueva la sesión por seguridad.

        return $this->redirectIntended(route('inicio')); // Vuelve a donde quería ir, o al inicio.
    }

    public function render()
    {
        return view('livewire.login');
    }
}
