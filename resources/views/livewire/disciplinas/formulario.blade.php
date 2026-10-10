{{--
    Vista de App\Livewire\Disciplinas\Formulario: sirve para crear y para editar (ver $disciplinaId en la clase).
    A la izquierda el nombre y el color; a la derecha, quiénes la tienen (al editar) y los botones.
--}}
@php
    // Clases de los controles (input), para no repetirlas en cada campo.
    $control = 'rounded-xl border-[1.5px] border-line px-3.5 py-[11px] font-normal outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15';
@endphp

<form wire:submit="guardar" class="grid gap-4">
    <div>
        <a href="{{ route('usuarios') }}" class="text-sm font-medium text-muted hover:text-brand">← Volver a profesionales y disciplinas</a>
        <h1 class="text-[22px] font-bold">{{ $disciplinaId ? 'Editar disciplina' : 'Nueva disciplina' }}</h1>
    </div>

    <div class="grid grid-cols-[1fr_22rem] items-start gap-4">
        {{-- Columna izquierda: nombre y color. --}}
        <x-formulario.seccion titulo="Disciplina">
            <x-formulario.campo nombre="Nombre" error="nombre" class="max-w-md">
                <input type="text" wire:model="nombre" class="{{ $control }}">
            </x-formulario.campo>

            {{--
                Color: se elige con el selector del navegador o escribiendo el hexa; los dos campos manejan el mismo valor.
                x-data activa Alpine en este bloque para armar la vista previa al instante, leyendo
                "color" y "nombre" del componente con $wire (sin ir al servidor).
            --}}
            <div class="grid gap-1.5 text-sm font-medium" x-data>
                <span>Color</span>
                <div class="flex flex-wrap items-center gap-3">
                    <input type="color" wire:model="color" class="h-[46px] w-14 cursor-pointer rounded-xl border-[1.5px] border-line bg-white p-1" title="Elegir color">
                    <input type="text" wire:model="color" maxlength="7" placeholder="#7c5cf0" class="{{ $control }} w-32 font-mono">

                    {{-- Vista previa: así se va a ver la etiqueta de esta disciplina en las pantallas. --}}
                    <span class="font-normal text-muted">Así se ve:</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :style="`color: ${$wire.color}; background-color: ${$wire.color}1a`"
                        x-text="$wire.nombre || 'Disciplina'"></span>
                </div>
                @error('color') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </div>
        </x-formulario.seccion>

        {{-- Columna derecha: queda fija al bajar con el scroll (sticky), así los botones están siempre a la vista. --}}
        <aside class="sticky top-4 grid gap-4">
            {{-- Quiénes la tienen: solo al editar y solo para mirar. Se cambia desde el formulario de cada profesional. --}}
            @if ($disciplinaId)
                <x-formulario.seccion titulo="Profesionales" ayuda="Quiénes tienen esta disciplina. Se cambia desde cada profesional.">
                    <div class="grid gap-1 text-sm">
                        @forelse ($profesionales as $profesional)
                            <a href="{{ route('usuarios.editar', $profesional) }}" wire:key="profesional-{{ $profesional->id }}" class="font-medium text-brand hover:text-brand-dark">{{ $profesional->name }}</a>
                        @empty
                            <p class="text-muted">Todavía nadie.</p>
                        @endforelse
                    </div>
                </x-formulario.seccion>
            @endif

            <div class="grid gap-2">
                <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
                {{-- Cancelar vuelve al listado sin guardar. --}}
                <a href="{{ route('usuarios') }}" class="rounded-xl bg-brand-soft px-4 py-2.5 text-center font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Cancelar</a>
            </div>
        </aside>
    </div>
</form>
