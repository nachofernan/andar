<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Página de inicio (la primera después de iniciar sesión).
 * Muestra "mis pacientes": los que atiende el usuario logueado, con el aviso
 * de mensajes sin leer de cada uno.
 *
 * Vista: resources/views/livewire/inicio.blade.php
 */
class Inicio extends Component
{
    /** Si está tildado, la lista también trae los pacientes dados de baja. */
    public bool $verInactivos = false;

    public function render()
    {
        $usuario = auth()->user();

        return view('livewire.inicio', [
            // "id del paciente => cantidad de mensajes sin leer" (ver User::mensajesSinLeer).
            'sinLeer' => $usuario->mensajesSinLeer(),
            'pacientes' => $usuario->pacientes()
                ->when(! $this->verInactivos, fn ($consulta) => $consulta->where('activo', true))
                ->orderBy('apellido')->orderBy('nombre')->get(),
        ]);
    }
}
