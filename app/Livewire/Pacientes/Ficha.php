<?php

namespace App\Livewire\Pacientes;

use App\Models\Paciente;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de un paciente: su cabecera (datos y equipo), el diagnóstico y los archivos de su historial.
 * El historial en sí (el chat) es otro componente, Mensajes, que se muestra adentro.
 * La ven Dirección y los profesionales del equipo del paciente.
 *
 * Los datos son de solo lectura, salvo el diagnóstico: lo puede cambiar cualquiera del equipo desde acá,
 * y el cambio queda anotado en el historial (ver Paciente::actualizar).
 *
 * Vista: resources/views/livewire/pacientes/ficha.blade.php
 */
class Ficha extends Component
{
    public Paciente $paciente;

    /** true mientras se está editando el diagnóstico: la vista muestra la ventana (modal) con el cuadro de texto. */
    public bool $editandoDiagnostico = false;

    /** Conectada al cuadro de texto del diagnóstico con wire:model. */
    public string $diagnostico = '';

    /** Recibe el paciente de la URL (/pacientes/{paciente}). Si el id no existe, Laravel responde 404. */
    public function mount(Paciente $paciente): void
    {
        $this->paciente = $paciente;
    }

    /** Clic en "Editar" del diagnóstico: abre la ventana con lo que hay cargado. */
    public function editarDiagnostico(): void
    {
        $this->soloEquipo();

        $this->resetValidation();
        $this->diagnostico = $this->paciente->diagnostico ?? '';
        $this->editandoDiagnostico = true;
    }

    /** Guarda el diagnóstico. El cambio queda como una entrada en el historial del paciente. */
    public function guardarDiagnostico(): void
    {
        $this->soloEquipo();

        // Solo se guarda si antes se abrió la ventana con "Editar": si no, $diagnostico está vacío y se borraría el que hay.
        if (! $this->editandoDiagnostico) {
            return;
        }

        $this->diagnostico = trim($this->diagnostico);

        $this->validate(
            ['diagnostico' => ['nullable', 'max:5000']],
            ['max' => 'El texto es demasiado largo.'],
        );

        // null en el equipo = no tocarlo. Vacío se guarda como null, igual que en el formulario.
        $this->paciente->actualizar(
            ['diagnostico' => $this->diagnostico !== '' ? $this->diagnostico : null],
            null,
            auth()->user(),
        );

        $this->editandoDiagnostico = false;

        // Avisa al historial (el componente Mensajes) para que muestre la entrada nueva.
        $this->dispatch('paciente-actualizado');
    }

    /** Cierra la ventana sin guardar. */
    public function cancelarDiagnostico(): void
    {
        $this->resetValidation();
        $this->editandoDiagnostico = false;
    }

    /**
     * El historial avisa cuando se envió un mensaje. Este método no hace nada: alcanza con que exista
     * para que la ficha se vuelva a armar y la lista de archivos muestre los recién adjuntados.
     */
    #[On('mensaje-enviado')]
    public function refrescar(): void {}

    /** El diagnóstico solo lo cambia el equipo del paciente (la misma regla que para ver su historial). */
    private function soloEquipo(): void
    {
        abort_unless($this->paciente->esDelEquipo(auth()->user()), 403);
    }

    public function render()
    {
        $usuario = auth()->user();

        // Dirección ve cualquier ficha; un profesional, solo las de sus pacientes. Si no, error 403.
        // Se comprueba acá y no solo al abrir, porque render corre cada vez que la ficha se muestra o se refresca:
        // así, si a alguien lo sacan del equipo con la ficha abierta, deja de verla.
        abort_unless($this->paciente->puedeVerlo($usuario), 403);

        // Trae el equipo y las disciplinas de cada profesional.
        $this->paciente->load('profesionales.disciplinas');

        $esDelEquipo = $this->paciente->esDelEquipo($usuario);

        return view('livewire.pacientes.ficha', [
            'esDelEquipo' => $esDelEquipo,
            // Todos los mensajes del paciente que tienen algún adjunto, del más nuevo al más viejo, para la lista
            // "Archivos". Son parte del historial: a quien no es del equipo no se le busca ni se le manda ninguno.
            'conArchivos' => $esDelEquipo
                ? $this->paciente->mensajes()->has('media')->with('autor', 'media')->latest()->latest('id')->get()
                : collect(),
        ]);
    }
}
