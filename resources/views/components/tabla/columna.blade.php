{{--
    Título de una columna por la que se puede ordenar (ver docs/tablas.md).
    Al hacer clic llama a ordenarPor() del componente (trait ConTabla) y muestra la flechita del sentido.

    Uso: <x-tabla.columna columna="paciente" :$orden :$ascendente>Paciente</x-tabla.columna>
--}}
@props(['columna', 'orden', 'ascendente'])

<th class="px-4 py-3 font-medium">
    <button type="button" wire:click="ordenarPor('{{ $columna }}')"
        class="flex cursor-pointer items-center gap-1 hover:text-ink">
        {{ $slot }}
        <span class="w-3 text-[10px]">{{ $orden === $columna ? ($ascendente ? '▲' : '▼') : '' }}</span>
    </button>
</th>
