{{--
    Vista de App\Livewire\Pacientes\Formulario: sirve para crear y para editar (ver $pacienteId en la clase).
    A la izquierda los datos, en secciones; a la derecha un panel con el equipo, el estado y los botones.
--}}
@php
    // Clases de los controles (input, textarea), para no repetirlas en cada campo.
    $control = 'rounded-xl border-[1.5px] border-line px-3.5 py-[11px] font-normal outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:text-base';
@endphp

<form wire:submit="guardar" class="grid gap-4">
    <div>
        <a href="{{ route('pacientes') }}" class="text-sm font-medium text-muted hover:text-brand">← Volver a pacientes</a>
    </div>

    {{-- Mobile (max-md:): una sola columna, los campos de a uno por renglón y el panel al final, sin quedar fijo (ver docs/mobile.md). --}}
    <div class="grid grid-cols-[1fr_22rem] items-start gap-4 max-md:grid-cols-1">
        {{-- Columna izquierda: los datos del paciente. --}}
        <div class="grid gap-4">
            <x-formulario.seccion titulo="Datos personales">
                <div class="grid grid-cols-2 gap-3 max-md:grid-cols-1">
                    <x-formulario.campo nombre="Nombre" error="nombre">
                        <input type="text" wire:model="nombre" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="Apellido" error="apellido">
                        <input type="text" wire:model="apellido" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="DNI" error="dni">
                        <input type="text" wire:model="dni" inputmode="numeric" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="Fecha de nacimiento" error="fecha_nacimiento">
                        <input type="date" wire:model="fecha_nacimiento" class="{{ $control }}">
                    </x-formulario.campo>
                </div>
            </x-formulario.seccion>

            <x-formulario.seccion titulo="Escolaridad y cobertura">
                <div class="grid grid-cols-2 gap-3 max-md:grid-cols-1">
                    <x-formulario.campo nombre="Obra social" opcional error="obra_social" class="col-span-2 max-md:col-span-1">
                        <input type="text" wire:model="obra_social" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="Escuela" opcional error="escuela">
                        <input type="text" wire:model="escuela" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="Grado" opcional error="grado">
                        <input type="text" wire:model="grado" class="{{ $control }}">
                    </x-formulario.campo>
                </div>
            </x-formulario.seccion>

            <x-formulario.seccion titulo="En el centro">
                <x-formulario.campo nombre="Ingreso al espacio" opcional error="fecha_ingreso" class="max-w-xs">
                    <input type="date" wire:model="fecha_ingreso" class="{{ $control }}">
                </x-formulario.campo>

                <x-formulario.campo nombre="Diagnóstico" opcional error="diagnostico">
                    <textarea wire:model="diagnostico" rows="5" class="{{ $control }}"></textarea>
                </x-formulario.campo>
            </x-formulario.seccion>
        </div>

        {{-- Columna derecha: queda fija al bajar con el scroll (sticky), así los botones están siempre a la vista. --}}
        <aside class="sticky top-4 grid gap-4 max-md:static">
            {{-- Equipo: un renglón por usuario. Los tildados quedan como profesionales del paciente. --}}
            <x-formulario.seccion titulo="Equipo" ayuda="Profesionales que lo atienden.">
                {{-- Cuántos hay tildados. Lo actualiza Alpine al instante, leyendo la propiedad "equipo" del componente. --}}
                <p class="-mt-2 text-xs text-muted" x-data>
                    <span x-text="$wire.equipo.length"></span> elegidos
                </p>

                <div class="-mx-2 grid max-h-[26rem] gap-1 overflow-y-auto">
                    @foreach ($usuarios as $usuario)
                        {{-- Todo el renglón es clickeable. has-[:checked] lo resalta cuando su casilla está tildada. --}}
                        <label wire:key="usuario-{{ $usuario->id }}"
                            class="flex cursor-pointer items-start gap-2.5 rounded-xl px-2 py-2 text-sm transition-colors hover:bg-brand-soft/50 has-[:checked]:bg-brand-soft">
                            <input type="checkbox" wire:model="equipo" value="{{ $usuario->id }}" class="mt-0.5 size-4">
                            <span class="grid gap-1">
                                <span class="font-medium">{{ $usuario->name }}</span>
                                {{-- Sus disciplinas, cada una con su color. --}}
                                @if ($usuario->disciplinas->isNotEmpty())
                                    <span class="flex flex-wrap gap-1">
                                        @foreach ($usuario->disciplinas as $disciplina)
                                            <x-disciplina.etiqueta :disciplina="$disciplina" />
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('equipo.*') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
            </x-formulario.seccion>

            {{-- Dar de baja: solo al editar. Un paciente nuevo siempre nace activo. --}}
            @if ($pacienteId)
                <x-formulario.seccion titulo="Estado">
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                        <input type="checkbox" wire:model="activo" class="mt-0.5 size-4">
                        <span>
                            <span class="font-medium">Activo</span>
                            <span class="block text-muted">Destildar para darlo de baja sin borrar sus datos.</span>
                        </span>
                    </label>
                </x-formulario.seccion>
            @endif

            <div class="grid gap-2">
                <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
                {{-- Cancelar vuelve a la ficha (editando) o a la lista (creando). --}}
                <a href="{{ $pacienteId ? route('pacientes.ver', $pacienteId) : route('pacientes') }}" class="rounded-xl bg-brand-soft px-4 py-2.5 text-center font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Cancelar</a>
            </div>
        </aside>
    </div>
</form>
