{{--
    Vista de App\Livewire\Usuarios\Formulario: sirve para crear y para editar (ver $usuarioId en la clase).
    A la izquierda los datos y el acceso (contraseña y si es administrador); a la derecha un panel con las disciplinas y los botones.
--}}
@php
    // Clases de los controles (input), para no repetirlas en cada campo.
    $control = 'rounded-xl border-[1.5px] border-line px-3.5 py-[11px] font-normal outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15';

@endphp

<form wire:submit="guardar" class="grid gap-4">
    <div>
        <a href="{{ route('usuarios') }}" class="text-sm font-medium text-muted hover:text-brand">← Volver a profesionales</a>
    </div>

    <div class="grid grid-cols-[1fr_22rem] items-start gap-4">
        {{-- Columna izquierda: datos y acceso. --}}
        <div class="grid gap-4">
            <x-formulario.seccion titulo="Datos">
                <div class="grid grid-cols-2 gap-3">
                    <x-formulario.campo nombre="Nombre" error="name" class="col-span-2">
                        <input type="text" wire:model="name" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="Email" error="email">
                        <input type="email" wire:model="email" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="Teléfono / WhatsApp" opcional error="telefono">
                        <input type="text" wire:model="telefono" inputmode="tel" class="{{ $control }}">
                    </x-formulario.campo>
                </div>
            </x-formulario.seccion>

            <x-formulario.seccion titulo="Acceso">
                {{--
                    Contraseña, a la vista (no con puntitos) porque la escribe Dirección para pasársela a la persona.
                    Al crear viene precargada; al editar arranca vacía y solo se cambia si se escribe algo.
                --}}
                <x-formulario.campo nombre="Contraseña" :opcional="(bool) $usuarioId" error="password" class="max-w-md">
                    <input type="text" wire:model="password" autocomplete="off" class="{{ $control }}"
                        placeholder="{{ $usuarioId ? 'Dejar vacío para no cambiarla' : '' }}">
                    <span class="font-normal text-muted">
                        {{ $usuarioId ? 'Si escribís una, reemplaza a la que tiene hoy.' : 'Con esta entra la primera vez; después la cambia desde Mi perfil.' }}
                    </span>
                </x-formulario.campo>

                {{-- Administrador: una sola casilla. Tildada = rol Dirección; sin tildar = rol Profesional (ver docs/roles.md). --}}
                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border-[1.5px] border-line p-3.5 text-sm transition-colors hover:border-brand has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="checkbox" wire:model="admin" class="mt-0.5 size-4">
                    <span>
                        <span class="font-semibold">Es administrador</span>
                        <span class="block text-muted">Puede cargar y editar pacientes, equipos, profesionales y disciplinas. Sin tildar, ve solo los pacientes que atiende.</span>
                    </span>
                </label>
                @error('admin') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
            </x-formulario.seccion>
        </div>

        {{-- Columna derecha: queda fija al bajar con el scroll (sticky), así los botones están siempre a la vista. --}}
        <aside class="sticky top-4 grid gap-4">
            {{-- Disciplinas: un renglón por cada una. Se pueden tildar varias, o ninguna. --}}
            <x-formulario.seccion titulo="Disciplinas" ayuda="Puede tener varias o ninguna.">
                <div class="-mx-2 grid gap-1">
                    @forelse ($todasLasDisciplinas as $disciplina)
                        {{-- Todo el renglón es clickeable. has-[:checked] lo resalta cuando su casilla está tildada. --}}
                        <label wire:key="disciplina-{{ $disciplina->id }}"
                            class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-2 text-sm transition-colors hover:bg-brand-soft/50 has-[:checked]:bg-brand-soft">
                            <input type="checkbox" wire:model="disciplinas" value="{{ $disciplina->id }}" class="size-4">
                            <x-disciplina.etiqueta :disciplina="$disciplina" />
                        </label>
                    @empty
                        <p class="px-2 text-sm text-muted">Todavía no hay disciplinas cargadas.</p>
                    @endforelse
                </div>
                @error('disciplinas.*') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
            </x-formulario.seccion>

            <div class="grid gap-2">
                <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
                {{-- Cancelar vuelve al listado sin guardar. --}}
                <a href="{{ route('usuarios') }}" class="rounded-xl bg-brand-soft px-4 py-2.5 text-center font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Cancelar</a>
            </div>
        </aside>
    </div>
</form>
