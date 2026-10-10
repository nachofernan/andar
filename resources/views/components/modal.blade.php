{{--
    Ventana modal: un recuadro encima de la pantalla, con el fondo oscurecido.

    Quién la abre y la cierra es el componente Livewire: se la dibuja dentro de un @if con una propiedad suya
    (true = abierta), y en "cerrar" se le pasa el nombre del método que la cierra. Ese método se llama al
    apretar la ✕, al hacer clic en el fondo oscuro o con la tecla Escape.

    Uso:
        @if ($editandoDiagnostico)
            <x-modal titulo="Diagnóstico" cerrar="cancelarDiagnostico">
                ...contenido...
            </x-modal>
        @endif
--}}
@props(['titulo', 'cerrar'])

{{-- Fondo oscuro que ocupa toda la pantalla. wire:click.self: solo cuenta el clic en el fondo, no adentro del recuadro. --}}
<div class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-ink/40 p-4"
    wire:click.self="{{ $cerrar }}"
    x-data x-on:keydown.escape.window="$wire.{{ $cerrar }}()">
    <div {{ $attributes->class('grid w-full max-w-2xl gap-4 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-ink/30') }} role="dialog" aria-modal="true">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold">{{ $titulo }}</h2>
            <button type="button" wire:click="{{ $cerrar }}" title="Cerrar"
                class="grid size-8 cursor-pointer place-items-center rounded-full text-muted transition-colors hover:bg-brand-soft hover:text-brand">✕</button>
        </div>

        {{ $slot }}
    </div>
</div>
