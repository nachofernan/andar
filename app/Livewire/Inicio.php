<?php

namespace App\Livewire;

use App\Models\Paciente;
use Livewire\Component;

/**
 * Página de inicio (la primera después de iniciar sesión).
 * Saluda y avisa en qué pacientes hay mensajes sin leer.
 *
 * Vista: resources/views/livewire/inicio.blade.php
 */
class Inicio extends Component
{
    public function render()
    {
        // "id del paciente => cantidad de mensajes sin leer" (ver User::mensajesSinLeer).
        $sinLeer = auth()->user()->mensajesSinLeer();

        return view('livewire.inicio', [
            'sinLeer' => $sinLeer,
            // Los pacientes que tienen alguno, para mostrar su nombre y el link a la ficha.
            'pacientes' => Paciente::whereIn('id', $sinLeer->keys())->orderBy('apellido')->orderBy('nombre')->get(),
        ]);
    }
}
