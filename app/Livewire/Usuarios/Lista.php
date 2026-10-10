<?php

namespace App\Livewire\Usuarios;

use App\Livewire\Concerns\ConTabla;
use App\Livewire\Concerns\SoloDireccion;
use App\Models\Disciplina;
use App\Models\User;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Pantalla "Profesionales y disciplinas" de administración: exclusiva de Dirección.
 * Es solo listado: la tabla de profesionales (los usuarios) y, al costado, el panel de disciplinas.
 * Crear y editar se hace en pantallas aparte (Usuarios\Formulario y Disciplinas\Formulario).
 *
 * La tabla se puede buscar, filtrar y ordenar (trait ConTabla; ver docs/tablas.md).
 *
 * Vista: resources/views/livewire/usuarios/lista.blade.php
 */
class Lista extends Component
{
    use ConTabla, SoloDireccion;

    /** Filtro por disciplina: el id de una, o vacío para no filtrar. Se recuerda durante la sesión (ver ConTabla). */
    #[Session]
    public string $disciplina = '';

    /** Columnas por las que se puede ordenar (ver ConTabla). */
    protected function columnasOrdenables(): array
    {
        return [
            'nombre' => fn (User $usuario) => $this->normalizar($usuario->name),
            // Alfabético por sus disciplinas (ya vienen ordenadas por nombre); con varias, manda la primera.
            // Quien no tiene ninguna lleva "~", que va después de cualquier letra: queda al final.
            'disciplinas' => fn (User $usuario) => $this->normalizar($usuario->disciplinas->pluck('nombre')->implode(', ')) ?: '~',
            // Cuántos pacientes atiende (ver withCount en render).
            'pacientes' => fn (User $usuario) => $usuario->pacientes_count,
        ];
    }

    public function render()
    {
        // with(...) trae las disciplinas y el rol de todos en una sola consulta cada uno.
        // withCount agrega pacientes_count: cuántos pacientes atiende cada uno (activos o no).
        // El orden lo pone ordenar(), más abajo.
        $usuarios = User::with('disciplinas', 'roles')->withCount('pacientes')->get()
            ->filter(fn (User $usuario) => $this->disciplina === '' || $usuario->disciplinas->contains('id', (int) $this->disciplina))
            ->filter(fn (User $usuario) => $this->coincide($usuario->name, $usuario->email));

        return view('livewire.usuarios.lista', [
            'usuarios' => $this->ordenar($usuarios),
            // Para el panel del costado y el desplegable del filtro. withCount agrega profesionales_count: cuántos profesionales tiene cada disciplina.
            'disciplinas' => Disciplina::withCount('profesionales')->orderBy('nombre')->get(),
        ]);
    }
}
