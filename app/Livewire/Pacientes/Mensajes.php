<?php

namespace App\Livewire\Pacientes;

use App\Models\Mensaje;
use App\Models\Paciente;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Historial del equipo sobre un paciente, con forma de chat: lo más viejo arriba, lo más nuevo abajo
 * y el cuadro para escribir al pie. Se muestra dentro de la ficha.
 *
 * Además de los mensajes que escriben los profesionales, muestra las entradas automáticas de la bitácora
 * (los cambios en los datos del paciente; ver Paciente::actualizar).
 *
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

    /** @var array<int, TemporaryUploadedFile> Archivos ya elegidos para adjuntar al mensaje que se está escribiendo. */
    public array $archivos = [];

    /**
     * Propiedad "de paso": acá deja el selector de archivos lo que se acaba de elegir.
     * Enseguida se pasa a $archivos (ver updatedNuevos), para que elegir de nuevo sume en vez de reemplazar.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $nuevos = [];

    // Tipo elegido en el filtro de la lista. Vacío = se muestran todos.
    public string $filtro = '';

    // Filtro por fechas: desde qué día y hasta qué día (los dos incluidos), en formato año-mes-día,
    // el que usa el input de fecha. Se puede cargar uno solo. Vacío = sin límite de ese lado.
    public string $desde = '';

    public string $hasta = '';

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

    /**
     * Livewire lo llama solo cuando el selector termina de subir lo elegido: lo suma a la lista de adjuntos
     * y deja la propiedad de paso vacía, lista para la próxima elección.
     */
    public function updatedNuevos(): void
    {
        $this->archivos = array_merge($this->archivos, $this->nuevos);
        $this->nuevos = [];
    }

    /** Clic en la ✕ de un adjunto ya elegido: lo saca de la lista (y borra su copia temporal). */
    public function quitarArchivo(int $posicion): void
    {
        if (! isset($this->archivos[$posicion])) {
            return;
        }

        $this->borrarCopiaTemporal($this->archivos[$posicion]);
        unset($this->archivos[$posicion]);
        $this->archivos = array_values($this->archivos); // Vuelve a numerar desde 0.
        $this->resetValidation(['archivos', 'archivos.*']);
    }

    /** Clic en "Borrar filtros": saca el filtro por tipo y las dos fechas, y vuelve a mostrar todo el historial. */
    public function borrarFiltros(): void
    {
        $this->reset('filtro', 'desde', 'hasta');
        $this->dispatch('ir-al-final'); // La vista baja el chat hasta el último mensaje.
    }

    /**
     * Livewire lo llama solo cada vez que cambia una propiedad desde la pantalla.
     * Si lo que cambió es un filtro, también se baja el chat hasta lo último de lo que quedó a la vista.
     */
    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['filtro', 'desde', 'hasta'], true)) {
            $this->dispatch('ir-al-final');
        }
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
                // Solo los tipos que escribe una persona: "actualizacion" lo pone la app sola.
                'tipo' => ['required', Rule::in(array_keys(Mensaje::tiposParaEscribir()))],
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
        $this->reset('cuerpo', 'tipo', 'archivos', 'nuevos');
        $this->envios++;

        // Avisa a la ficha (para que refresque su lista de archivos) y a la vista (para bajar el chat hasta lo último).
        $this->dispatch('mensaje-enviado');
    }

    /**
     * La ficha avisa cuando alguien cambió un dato del paciente desde ahí (el diagnóstico). Este método no hace
     * nada: alcanza con que exista para que el historial se vuelva a armar y muestre la entrada nueva.
     */
    #[On('paciente-actualizado')]
    public function refrescar(): void {}

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

        // Las fechas del filtro solo se usan si vienen bien escritas (año-mes-día); si no, se ignoran.
        $esFecha = fn (string $texto) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) === 1;

        return view('livewire.pacientes.mensajes', [
            'esDelEquipo' => $esDelEquipo,
            // ¿Hay algún filtro puesto? Para elegir el texto cuando la lista queda vacía.
            'hayFiltro' => $this->filtro !== '' || $this->desde !== '' || $this->hasta !== '',
            'tipos' => Mensaje::TIPOS,                          // Todos: para el filtro y las etiquetas.
            'tiposParaEscribir' => Mensaje::tiposParaEscribir(), // Sin los automáticos: para el desplegable de escribir.
            // A quien no es del equipo no se le busca ni se le manda ningún mensaje.
            'mensajes' => $esDelEquipo
                ? $this->paciente->mensajes()
                    ->with('autor.disciplinas', 'media') // "media" son los adjuntos.
                    ->when($this->filtro !== '', fn ($consulta) => $consulta->where('tipo', $this->filtro))
                    // whereDate compara solo el día, sin la hora: así "hasta el 10" incluye todo lo del 10.
                    ->when($esFecha($this->desde), fn ($consulta) => $consulta->whereDate('created_at', '>=', $this->desde))
                    ->when($esFecha($this->hasta), fn ($consulta) => $consulta->whereDate('created_at', '<=', $this->hasta))
                    ->oldest()       // Como un chat: el más viejo arriba, el más nuevo abajo.
                    ->oldest('id')   // Si dos tienen la misma hora, primero el que se cargó antes.
                    ->get()
                : collect(),
        ]);
    }
}
