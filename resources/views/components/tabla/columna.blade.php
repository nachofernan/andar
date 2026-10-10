{{--
    Título de una columna por la que se puede ordenar (ver docs/tablas.md).
    Al hacer clic llama a ordenarPor() del componente (trait ConTabla) y muestra la flechita del sentido.

    Uso: <x-tabla.columna columna="paciente" :$orden :$ascendente>Paciente</x-tabla.columna>
    Con "derecha" el título queda alineado a la derecha (para columnas de números) y la flechita pasa
    a la izquierda del texto, así el texto queda al ras de los números de abajo.
--}}
@props(['columna', 'orden', 'ascendente', 'derecha' => false])

<th class="px-4 py-3 font-medium">
    <button type="button" wire:click="ordenarPor('{{ $columna }}')"
        class="flex cursor-pointer items-center gap-1 hover:text-ink {{ $derecha ? 'ml-auto flex-row-reverse' : '' }}">
        {{ $slot }}
        <span class="w-3 text-[10px]">{{ $orden === $columna ? ($ascendente ? '▲' : '▼') : '' }}</span>
    </button>
</th>
