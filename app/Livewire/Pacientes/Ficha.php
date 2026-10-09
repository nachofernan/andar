<?php

namespace App\Livewire\Pacientes;

use App\Models\Paciente;
use Livewire\Component;

/**
 * Ficha de un paciente: muestra todos sus datos y su equipo. Solo lectura.
 * La ven Dirección y los profesionales del equipo del paciente.
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
        // Dirección ve cualquier ficha; un profesional, solo las de sus pacientes. Si no, error 403.
        // Se comprueba acá y no solo al abrir, porque render corre cada vez que la ficha se muestra o se refresca:
        // así, si a alguien lo sacan del equipo con la ficha abierta, deja de verla.
        abort_unless($this->paciente->puedeVerlo(auth()->user()), 403);

        // Trae el equipo y las disciplinas de cada profesional.
        $this->paciente->load('profesionales.disciplinas');

        return view('livewire.pacientes.ficha');
    }
}
