<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConTabla;
use App\Models\Mensaje;
use App\Models\Paciente;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Página de inicio (la primera después de iniciar sesión).
 * Muestra un resumen en tarjetas y la tabla "Mis pacientes": los que atiende el usuario logueado,
 * con el aviso de mensajes sin leer y la última actividad de cada uno.
 *
 * La tabla se puede buscar, filtrar y ordenar (trait ConTabla; ver docs/tablas.md).
 * Los pacientes con mensajes sin leer van siempre arriba, se ordene por lo que se ordene.
 *
 * Vista: resources/views/livewire/inicio.blade.php
 */
class Inicio extends Component
{
    use ConTabla;

    /** Filtro de estado: "activos" (por defecto), "inactivos" o "todos". Se recuerda durante la sesión (ver ConTabla). */
    #[Session]
    public string $estado = 'activos';

    /** Si está tildado, la tabla muestra solo los pacientes con mensajes sin leer. */
    #[Session]
    public bool $soloSinLeer = false;

    /** Columnas por las que se puede ordenar (ver ConTabla). */
    protected function columnasOrdenables(): array
    {
        return [
            'paciente' => fn (Paciente $paciente) => $this->normalizar($paciente->nombre_completo),
            // En negativo: el que nació más tarde es el de menor edad.
            'edad' => fn (Paciente $paciente) => -$paciente->fecha_nacimiento->timestamp,
            // Por la fecha del último mensaje. Sin mensajes vale 0: va al principio.
            'actividad' => fn (Paciente $paciente) => $paciente->ultimoMensaje?->created_at->timestamp ?? 0,
        ];
    }

    public function render()
    {
        $usuario = auth()->user();

        // "id del paciente => cantidad de mensajes sin leer" (ver User::mensajesSinLeer).
        $sinLeer = $usuario->mensajesSinLeer();

        // Todos mis pacientes, sin filtrar: de acá salen las tarjetas y, filtrando, la tabla.
        $misPacientes = $usuario->pacientes()->get();

        // El último mensaje de cada uno de mis pacientes (con quién lo escribió), en una sola consulta.
        // Se traen del más viejo al más nuevo y keyBy se queda con el último de cada paciente.
        // Solo se piden estas columnas: el texto (cifrado) no hace falta y no se muestra acá.
        $ultimos = Mensaje::with('autor')
            ->whereIn('paciente_id', $misPacientes->modelKeys())
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'paciente_id', 'user_id', 'created_at'])
            ->keyBy('paciente_id');

        // Se lo cuelga a cada paciente para usarlo como $paciente->ultimoMensaje (null si no tiene mensajes).
        $misPacientes->each(fn (Paciente $paciente) => $paciente->setRelation('ultimoMensaje', $ultimos->get($paciente->id)));

        $filtrados = $misPacientes
            ->filter(fn (Paciente $paciente) => match ($this->estado) {
                'activos' => $paciente->activo,
                'inactivos' => ! $paciente->activo,
                default => true,
            })
            ->filter(fn (Paciente $paciente) => ! $this->soloSinLeer || $sinLeer->has($paciente->id))
            // Se busca en "Apellido, Nombre" y en "Nombre Apellido", para que sirva escrito de las dos formas.
            ->filter(fn (Paciente $paciente) => $this->coincide($paciente->nombre_completo, "{$paciente->nombre} {$paciente->apellido}"));

        // Primero se ordena por la columna elegida y después se separan en dos grupos, respetando ese orden:
        // los que tienen mensajes sin leer van arriba y el resto abajo.
        [$conSinLeer, $alDia] = $this->ordenar($filtrados)->partition(fn (Paciente $paciente) => $sinLeer->has($paciente->id));

        return view('livewire.inicio', [
            'sinLeer' => $sinLeer,
            'pacientes' => $conSinLeer->concat($alDia),
            // Para distinguir "no tenés pacientes" de "ninguno coincide con los filtros".
            'tienePacientes' => $misPacientes->isNotEmpty(),
            // Tarjetas de arriba: texto => número. No cambian con los filtros de la tabla.
            'resumen' => [
                'Pacientes activos' => $misPacientes->where('activo', true)->count(),
                'Pacientes con mensajes sin leer' => $sinLeer->count(),
                'Mensajes sin leer' => $sinLeer->sum(),
            ],
        ]);
    }
}
