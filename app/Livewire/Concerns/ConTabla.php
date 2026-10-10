<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;

/**
 * Para las pantallas con una tabla que se puede buscar y ordenar (ver docs/tablas.md).
 * Se agrega a un componente Livewire con "use ConTabla;".
 *
 * La tabla se filtra y se ordena acá, en PHP, sobre la lista ya traída de la base (no en la consulta):
 * el DNI está cifrado y la base no puede buscarlo, y además así la búsqueda ignora tildes y mayúsculas.
 *
 * #[Session] hace que Livewire recuerde ese valor mientras dure la sesión del usuario: si busca u ordena,
 * entra a un registro y vuelve, la tabla sigue como la dejó. Cada pantalla lo usa también en sus filtros.
 */
trait ConTabla
{
    /** Lo que se escribió en el buscador. */
    #[Session]
    public string $buscar = '';

    /** Columna por la que está ordenada la tabla: una de las claves de columnasOrdenables(). */
    #[Session]
    public string $orden = '';

    /** true = de menor a mayor (A→Z); false = al revés. */
    #[Session]
    public bool $ascendente = true;

    /**
     * Las columnas por las que se puede ordenar. Cada pantalla define las suyas.
     * La clave es el nombre de la columna y el valor una función que, dada una fila, devuelve por qué valor se ordena.
     * La primera es el orden con el que abre la pantalla.
     *
     * @return array<string, \Closure>
     */
    abstract protected function columnasOrdenables(): array;

    /** Livewire lo llama solo al abrir la pantalla: arranca ordenada por la primera columna, salvo que ya haya un orden recordado. */
    public function mountConTabla(): void
    {
        if (! array_key_exists($this->orden, $this->columnasOrdenables())) {
            $this->orden = array_key_first($this->columnasOrdenables());
        }
    }

    /** Clic en el título de una columna: ordena por ella; si ya estaba ordenada por esa, invierte el sentido. */
    public function ordenarPor(string $columna): void
    {
        if (! array_key_exists($columna, $this->columnasOrdenables())) {
            return; // No es una columna de esta tabla.
        }

        if ($this->orden === $columna) {
            $this->ascendente = ! $this->ascendente;
        } else {
            $this->orden = $columna;
            $this->ascendente = true;
        }
    }

    /** Devuelve las filas ordenadas por la columna y el sentido elegidos. */
    protected function ordenar(Collection $filas): Collection
    {
        $columnas = $this->columnasOrdenables();
        // Si $orden trae algo raro (se puede manipular desde el navegador), se usa la primera columna.
        $valor = $columnas[$this->orden] ?? reset($columnas);

        // Primero por la primera columna (el nombre) y después por la elegida: así, cuando dos filas empatan
        // en la elegida (ej. mismo estado), entre ellas quedan por nombre. El segundo orden respeta el primero.
        return $filas
            ->sortBy(reset($columnas))
            ->sortBy($valor, SORT_REGULAR, ! $this->ascendente)
            ->values();
    }

    /** ¿Alguno de estos textos contiene lo que se escribió en el buscador? Con el buscador vacío, siempre sí. */
    protected function coincide(?string ...$textos): bool
    {
        $buscado = $this->normalizar($this->buscar);

        if ($buscado === '') {
            return true;
        }

        foreach ($textos as $texto) {
            if (str_contains($this->normalizar($texto ?? ''), $buscado)) {
                return true;
            }
        }

        return false;
    }

    /** Pasa un texto a minúsculas y sin tildes, para que "gomez" encuentre "Gómez" y "Álvarez" se ordene en la A. */
    protected function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }
}
