<?php

namespace App\Livewire\Concerns;

/**
 * Para las pantallas que solo puede usar Dirección (ver docs/roles.md).
 * Se agrega a un componente Livewire con "use SoloDireccion;".
 *
 * Livewire llama solo a este método cada vez que el componente se usa: al abrir la pantalla
 * y también en cada acción (guardar, editar, etc.). Si el usuario no es de Dirección, corta con error 403.
 */
trait SoloDireccion
{
    public function bootSoloDireccion(): void
    {
        abort_unless(auth()->user()?->esDireccion(), 403);
    }
}
