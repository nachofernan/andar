<?php

namespace App\Livewire\Pacientes;

use App\Livewire\Concerns\ConTabla;
use App\Livewire\Concerns\SoloDireccion;
use App\Models\Paciente;
use App\Models\User;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Listado administrativo de pacientes (alta, estado, equipo asignado): exclusivo de Dirección.
 * Cada fila lleva directo a editar al paciente (ver docs/pacientes.md).
 * Acá no se avisan los mensajes sin leer: eso es del Inicio.
 *
 * La tabla se puede buscar, filtrar y ordenar (trait ConTabla; ver docs/tablas.md).
 *
 * Vista: resources/views/livewire/pacientes/lista.blade.php
 */
class Lista extends Component
{
    use ConTabla, SoloDireccion;

    /** Filtro de estado: "activos" (por defecto), "inactivos" o "todos". Se recuerda durante la sesión (ver ConTabla). */
    #[Session]
    public string $estado = 'activos';

    /** Filtro por profesional: el id de un usuario, o vacío para no filtrar. */
    #[Session]
    public string $profesional = '';

    /** Columnas por las que se puede ordenar (ver ConTabla). */
    protected function columnasOrdenables(): array
    {
        return [
            'paciente' => fn (Paciente $paciente) => $this->normalizar($paciente->nombre_completo),
            // Por la fecha de ingreso al espacio. Los que no la tienen cargada valen 0: van al principio.
            'ingreso' => fn (Paciente $paciente) => $paciente->fecha_ingreso?->timestamp ?? 0,
            // Primero los activos.
            'estado' => fn (Paciente $paciente) => $paciente->activo ? 0 : 1,
        ];
    }

    public function render()
    {
        // with(...) trae el equipo de todos, y las disciplinas de cada profesional (para sus puntitos de color),
        // en una sola consulta cada cosa. El orden lo pone ordenar(), más abajo.
        $pacientes = Paciente::with('profesionales.disciplinas')->get()
            ->filter(fn (Paciente $paciente) => match ($this->estado) {
                'activos' => $paciente->activo,
                'inactivos' => ! $paciente->activo,
                default => true,
            })
            ->filter(fn (Paciente $paciente) => $this->profesional === '' || $paciente->profesionales->contains('id', (int) $this->profesional))
            // Se busca por nombre (escrito de las dos formas) y por DNI, con o sin puntos.
            ->filter(fn (Paciente $paciente) => $this->coincide(
                $paciente->nombre_completo,
                "{$paciente->nombre} {$paciente->apellido}",
                $paciente->dni,
                $paciente->dni_con_puntos,
            ));

        return view('livewire.pacientes.lista', [
            'pacientes' => $this->ordenar($pacientes),
            // Para el desplegable del filtro por profesional.
            'profesionales' => User::orderBy('name')->get(),
        ]);
    }
}
