<?php

namespace App\Livewire\Pacientes;

use App\Livewire\Concerns\SoloDireccion;
use App\Models\Paciente;
use Livewire\Component;

/**
 * Listado administrativo de pacientes (alta, estado, equipo asignado): exclusivo de Dirección.
 * Cada fila lleva directo a editar al paciente (ver docs/pacientes.md).
 *
 * Vista: resources/views/livewire/pacientes/lista.blade.php
 */
class Lista extends Component
{
    use SoloDireccion;

    public function render()
    {
        return view('livewire.pacientes.lista', [
            // with('profesionales') trae el equipo de todos en una sola consulta.
            'pacientes' => Paciente::with('profesionales')->orderBy('apellido')->orderBy('nombre')->get(),
            // "id del paciente => cantidad de mensajes sin leer" de Dirección (ver User::mensajesSinLeer).
            'sinLeer' => auth()->user()->mensajesSinLeer(),
        ]);
    }
}
