<?php

namespace App\Livewire\Pacientes;

use App\Models\Paciente;
use Livewire\Component;

/**
 * Lista de todos los pacientes del centro, activos e inactivos.
 *
 * Vista: resources/views/livewire/pacientes/lista.blade.php
 */
class Lista extends Component
{
    public function render()
    {
        return view('livewire.pacientes.lista', [
            // with('profesionales') trae el equipo de todos en una sola consulta.
            'pacientes' => Paciente::with('profesionales')->orderBy('apellido')->orderBy('nombre')->get(),
        ]);
    }
}
