<?php

namespace App\Livewire\Pacientes;

use App\Models\Mensaje;
use App\Models\Paciente;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Historial de mensajes del equipo sobre un paciente. Se muestra dentro de la ficha.
 * Solo los profesionales del equipo del paciente pueden verlo, escribir y bajar los archivos.
 * Los mensajes no se editan ni se borran.
 *
 * Vista: resources/views/livewire/pacientes/mensajes.blade.php
 */
class Mensajes extends Component
{
    use WithFileUploads; // Permite subir archivos desde el formulario.

    // Lo pasa la ficha: <livewire:pacientes.mensajes :paciente="$paciente" />
    public Paciente $paciente;

    // Conectadas al formulario de la vista con wire:model.
    public string $cuerpo = '';

    public string $tipo = 'mensaje';

    /** @var array<int, TemporaryUploadedFile> Archivos elegidos para adjuntar. */
    public array $archivos = [];

    // Tipo elegido en el filtro de la lista. Vacío = se muestran todos.
    public string $filtro = '';

    // Hasta cuándo había visto el historial ANTES de esta visita. Sirve para marcar como "Nuevo"
    // lo que llegó después. null = nunca lo había abierto.
    public ?string $vistoHasta = null;

    // Cuenta los envíos. La vista lo usa para "reiniciar" el selector de archivos después de cada uno.
    public int $envios = 0;

    /** Al abrir la ficha: si es del equipo, anota lo que ya había visto y marca el historial como leído. */
    public function mount(): void
    {
        $usuario = auth()->user();

        if (! $this->paciente->esDelEquipo($usuario)) {
            return;
        }

        $this->vistoHasta = $this->paciente->profesionales()->whereKey($usuario->id)->first()->pivot->mensajes_vistos_at;
        $this->marcarComoLeido();
    }

    /** Se ejecuta al enviar el formulario: valida y guarda el mensaje (y sus adjuntos) a nombre del usuario con sesión. */
    public function enviar(): void
    {
        // Se vuelve a comprobar acá, aunque la vista no muestre el formulario a quien no es del equipo.
        abort_unless($this->paciente->esDelEquipo(auth()->user()), 403);

        $this->cuerpo = trim($this->cuerpo);

        $datos = $this->validate(
            [
                'cuerpo' => ['required', 'max:5000'], // Obligatorio aunque haya archivos.
                'tipo' => ['required', Rule::in(array_keys(Mensaje::TIPOS))],
                'archivos' => ['array', 'max:'.Mensaje::MAX_ADJUNTOS],
                'archivos.*' => ['file', 'mimes:'.implode(',', Mensaje::EXTENSIONES_ADJUNTO), 'max:'.Mensaje::MAX_KB_ADJUNTO],
            ],
            [
                'cuerpo.required' => 'Escribí un mensaje.',
                'cuerpo.max' => 'El texto es demasiado largo.',
                'tipo.*' => 'Elegí un tipo de la lista.',
                'archivos.max' => 'Se pueden adjuntar hasta '.Mensaje::MAX_ADJUNTOS.' archivos por mensaje.',
                'archivos.*.mimes' => 'Solo se aceptan PDF, Word, Excel e imágenes (JPG o PNG).',
                'archivos.*.max' => 'Cada archivo puede pesar hasta '.(Mensaje::MAX_KB_ADJUNTO / 1024).' MB.',
                'archivos.*.file' => 'No se pudo subir el archivo. Probá de nuevo.',
            ],
        );

        $mensaje = $this->paciente->mensajes()->create([
            'cuerpo' => $datos['cuerpo'],
            'tipo' => $datos['tipo'],
            'user_id' => auth()->id(),
        ]);

        foreach ($this->archivos as $archivo) {
            $mensaje->adjuntar($archivo); // Lo guarda cifrado (ver Mensaje::adjuntar).
            $this->borrarCopiaTemporal($archivo);
        }

        // Quien escribe está mirando el historial: se le marca como leído hasta ahora.
        $this->marcarComoLeido();

        // Deja el formulario como al principio (texto vacío, tipo "Mensaje" y sin archivos).
        $this->reset('cuerpo', 'tipo', 'archivos');
        $this->envios++;
    }

    /**
     * Borra lo que la subida dejó SIN cifrar en la carpeta temporal de Livewire (livewire-tmp).
     * Son dos archivos: el archivo en sí y, al lado, un ".json" con su nombre original.
     * $archivo->delete() borra solo el primero, por eso el segundo se borra a mano.
     */
    private function borrarCopiaTemporal(TemporaryUploadedFile $archivo): void
    {
        $archivo->delete();

        FileUploadConfiguration::storage()->delete(FileUploadConfiguration::path($archivo->getFilename().'.json'));
    }

    /** Anota que el usuario con sesión vio el historial de este paciente hasta este momento. */
    private function marcarComoLeido(): void
    {
        $this->paciente->profesionales()->updateExistingPivot(auth()->id(), ['mensajes_vistos_at' => now()]);
    }

    public function render()
    {
        $esDelEquipo = $this->paciente->esDelEquipo(auth()->user());

        return view('livewire.pacientes.mensajes', [
            'esDelEquipo' => $esDelEquipo,
            'tipos' => Mensaje::TIPOS,
            // A quien no es del equipo no se le busca ni se le manda ningún mensaje.
            'mensajes' => $esDelEquipo
                ? $this->paciente->mensajes()
                    ->with('autor.disciplinas', 'media') // "media" son los adjuntos.
                    ->when($this->filtro !== '', fn ($consulta) => $consulta->where('tipo', $this->filtro))
                    ->latest()       // El más nuevo arriba.
                    ->latest('id')   // Si dos tienen la misma hora, primero el que se cargó último.
                    ->get()
                : collect(),
            // Todos los mensajes del paciente que tienen algún adjunto, para la lista "Archivos del paciente".
            // No depende del filtro de arriba.
            'conArchivos' => $esDelEquipo
                ? $this->paciente->mensajes()->has('media')->with('autor', 'media')->latest()->latest('id')->get()
                : collect(),
        ]);
    }
}
