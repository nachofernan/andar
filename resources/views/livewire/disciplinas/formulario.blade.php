{{--
    Vista de App\Livewire\Disciplinas\Formulario: sirve para crear y para editar (ver $disciplinaId en la clase).
    A la izquierda el nombre y el color; a la derecha, los profesionales que la tienen y los botones.
--}}
@php
    // Clases de los controles (input), para no repetirlas en cada campo.
    $control = 'rounded-xl border-[1.5px] border-line px-3.5 py-[11px] font-normal outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15';
@endphp

<form wire:submit="guardar" class="grid gap-4">
    <div>
        <a href="{{ route('usuarios') }}" class="text-sm font-medium text-muted hover:text-brand">← Volver a profesionales y disciplinas</a>
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
            {{-- Profesionales: un renglón por usuario. Los tildados quedan con esta disciplina. --}}
            <x-formulario.seccion titulo="Profesionales" ayuda="Quiénes tienen esta disciplina.">
                {{-- Cuántos hay tildados. Lo actualiza Alpine al instante, leyendo la propiedad "profesionales" del componente. --}}
                <p class="-mt-2 text-xs text-muted" x-data>
                    <span x-text="$wire.profesionales.length"></span> elegidos
                </p>

                <div class="-mx-2 grid max-h-[26rem] gap-1 overflow-y-auto">
                    @foreach ($usuarios as $usuario)
                        {{-- Todo el renglón es clickeable. has-[:checked] lo resalta cuando su casilla está tildada. --}}
                        <label wire:key="usuario-{{ $usuario->id }}"
                            class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-2 text-sm transition-colors hover:bg-brand-soft/50 has-[:checked]:bg-brand-soft">
                            <input type="checkbox" wire:model="profesionales" value="{{ $usuario->id }}" class="size-4">
                            <span class="font-medium">{{ $usuario->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('profesionales.*') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
            </x-formulario.seccion>

            <div class="grid gap-2">
                <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
                {{-- Cancelar vuelve al listado sin guardar. --}}
                <a href="{{ route('usuarios') }}" class="rounded-xl bg-brand-soft px-4 py-2.5 text-center font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Cancelar</a>
            </div>
        </aside>
    </div>
</form>
