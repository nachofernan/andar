<?php

namespace App\Livewire\Pacientes;

use App\Models\Paciente;
use Livewire\Component;

/**
 * Lista de pacientes, activos e inactivos. Dirección ve todos; un profesional, solo los suyos.
 *
 * Vista: resources/views/livewire/pacientes/lista.blade.php
 */
class Lista extends Component
{
    public function render()
    {
        $usuario = auth()->user();

        return view('livewire.pacientes.lista', [
            // Dirección ve todos los pacientes; un profesional, solo los que atiende.
            // with('profesionales') trae el equipo de todos en una sola consulta.
            'pacientes' => Paciente::with('profesionales')
                ->unless($usuario->esDireccion(), fn ($consulta) => $consulta->whereRelation('profesionales', 'users.id', $usuario->id))
                ->orderBy('apellido')->orderBy('nombre')->get(),
            // "id del paciente => cantidad de mensajes sin leer" del usuario con sesión (ver User::mensajesSinLeer).
            'sinLeer' => $usuario->mensajesSinLeer(),
        ]);
    }
}
