<?php

namespace App\Livewire\Pacientes;

use App\Models\Paciente;
use Livewire\Component;

/**
 * Ficha de un paciente: muestra todos sus datos y su equipo. Solo lectura.
 *
 * Vista: resources/views/livewire/pacientes/ficha.blade.php
 */
class Ficha extends Component
{
    public Paciente $paciente;

    /** Recibe el paciente de la URL (/pacientes/{paciente}). Si el id no existe, Laravel responde 404. */
    public function mount(Paciente $paciente): void
    {
        $this->paciente = $paciente;
    }

    public function render()
    {
        // Trae el equipo y la disciplina de cada profesional.
        $this->paciente->load('profesionales.disciplina');

        return view('livewire.pacientes.ficha');
    }
}
