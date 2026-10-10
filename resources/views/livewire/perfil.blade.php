{{--
    Vista de App\Livewire\Perfil: datos propios y cambio de contraseña (opcional).
    Misma forma que los formularios de administración (ver docs/estilos.md): a la izquierda los datos,
    en secciones; a la derecha un panel fijo con lo que no se cambia desde acá y el botón Guardar.
--}}
@php
    // Clases de los controles (input), para no repetirlas en cada campo.
    $control = 'rounded-xl border-[1.5px] border-line px-3.5 py-[11px] font-normal outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15';

    $usuario = auth()->user();
@endphp

<form wire:submit="guardar" class="grid gap-4">
    <h1 class="text-[22px] font-bold">Mi perfil</h1>

    <div class="grid grid-cols-[1fr_22rem] items-start gap-4">
        {{-- Columna izquierda: lo que cada uno puede cambiar. --}}
        <div class="grid gap-4">
            <x-formulario.seccion titulo="Mis datos">
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

            {{-- Cambio de contraseña: opcional, dejar en blanco para no cambiarla. --}}
            <x-formulario.seccion titulo="Cambiar contraseña" ayuda="Dejá estos campos en blanco para no cambiarla.">
                <x-formulario.campo nombre="Contraseña actual" error="contrasena_actual" class="max-w-md">
                    <input type="password" wire:model="contrasena_actual" autocomplete="current-password" class="{{ $control }}">
                </x-formulario.campo>

                <div class="grid grid-cols-2 gap-3">
                    <x-formulario.campo nombre="Contraseña nueva" error="contrasena_nueva">
                        <input type="password" wire:model="contrasena_nueva" autocomplete="new-password" class="{{ $control }}">
                    </x-formulario.campo>

                    <x-formulario.campo nombre="Repetir contraseña nueva">
                        <input type="password" wire:model="contrasena_nueva_confirmation" autocomplete="new-password" class="{{ $control }}">
                    </x-formulario.campo>
                </div>
            </x-formulario.seccion>
        </div>

        {{-- Columna derecha: queda fija al bajar con el scroll (sticky), así el botón está siempre a la vista. --}}
        <aside class="sticky top-4 grid gap-4">
            {{-- Solo para mirar: el rol y las disciplinas los carga Dirección desde Administración. --}}
            <x-formulario.seccion titulo="En el centro" ayuda="Esto lo carga Dirección.">
                <div class="grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted">Rol</span>
                        @if ($usuario->esDireccion())
                            <span class="rounded-full bg-accent/10 px-2.5 py-1 text-xs font-semibold text-accent">Admin</span>
                        @else
                            <span class="font-medium">Profesional</span>
                        @endif
                    </div>

                    <div class="grid gap-1.5">
                        <span class="text-muted">Disciplinas</span>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($usuario->disciplinas as $disciplina)
                                <x-disciplina.etiqueta :disciplina="$disciplina" />
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-formulario.seccion>

            <div class="grid gap-2">
                <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>

                {{-- Aviso después de guardar bien (ver $guardado en la clase). --}}
                @if ($guardado)
                    <p class="rounded-xl bg-accent/10 px-3.5 py-2.5 text-center text-sm font-semibold text-accent">Datos actualizados.</p>
                @endif
            </div>
        </aside>
    </div>
</form>
