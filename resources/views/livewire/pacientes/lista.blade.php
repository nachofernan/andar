{{-- Vista de App\Livewire\Pacientes\Lista: listado administrativo, con buscador, filtros y orden. --}}
<div class="grid gap-4">
    <div class="grid gap-2 rounded-[20px] bg-white p-2 shadow-[0_10px_40px] shadow-brand/15">
        {{-- Cabecera de la tarjeta: título con la cantidad y, a la derecha, el link para cargar uno nuevo. --}}
        <div class="flex items-center justify-between gap-3 px-4 pt-4">
            <h1 class="flex items-center gap-2 font-semibold">
                Pacientes
                {{-- Cuántos hay en la tabla en este momento (cambia con el buscador y los filtros). --}}
                <span class="rounded-full bg-brand-soft px-3 py-1 text-sm font-semibold text-brand">{{ $pacientes->count() }}</span>
            </h1>
            {{-- Link simple (sin fondo), subrayado al pasar el mouse. --}}
            <a href="{{ route('pacientes.crear') }}" class="font-semibold text-brand hover:underline">+ Nuevo</a>
        </div>

        {{-- Buscador y filtros. wire:model.live vuelve a armar la tabla apenas cambian. --}}
        <div class="flex flex-wrap items-center gap-3 px-4 pt-2">
            {{-- debounce: espera a que se deje de escribir un instante, para no consultar en cada tecla. --}}
            {{-- Mobile (max-md:): buscador y filtros a todo el ancho, con letra de 16 px para que el iPhone no haga zoom. --}}
            <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o DNI…"
                class="mr-auto w-72 rounded-xl border-[1.5px] border-line px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:w-full max-md:text-base">

            <select wire:model.live="profesional"
                class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:w-full max-md:text-base">
                <option value="">Todos los profesionales</option>
                @foreach ($profesionales as $usuario)
                    <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="estado"
                class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:w-full max-md:text-base">
                <option value="activos">Activos</option>
                <option value="inactivos">Inactivos</option>
                <option value="todos">Todos los estados</option>
            </select>
        </div>

        {{-- En mobile se ve como lista: sin títulos y solo con la columna "Paciente" (ver docs/mobile.md). --}}
        <table class="w-full text-left text-sm">
            <thead class="text-muted max-md:hidden">
                <tr>
                    <x-tabla.columna columna="paciente" :$orden :$ascendente>Paciente</x-tabla.columna>
                    <x-tabla.columna columna="ingreso" :$orden :$ascendente>Ingreso</x-tabla.columna>
                    <th class="px-4 py-3 font-medium">Equipo</th>
                    <x-tabla.columna columna="estado" :$orden :$ascendente>Estado</x-tabla.columna>
                </tr>
            </thead>
            <tbody>
                @forelse ($pacientes as $paciente)
                    {{-- Toda la fila lleva a editar al paciente (esta pantalla es de administración); el nombre además es un link común. --}}
                    <tr wire:key="paciente-{{ $paciente->id }}" onclick="window.location = '{{ route('pacientes.editar', $paciente) }}'"
                        class="cursor-pointer border-t border-line transition-colors hover:bg-brand-soft/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('pacientes.editar', $paciente) }}" class="font-semibold text-brand hover:text-brand-dark">{{ $paciente->nombre_completo }}</a>
                            {{-- Solo mobile: como la columna Estado no se ve, los inactivos llevan la etiqueta al lado del nombre. --}}
                            @if (! $paciente->activo)
                                <span class="ml-1 rounded-full bg-line px-2 py-0.5 text-xs font-semibold text-muted md:hidden">Inactivo</span>
                            @endif
                            {{-- DNI y edad van juntos debajo del nombre, más chicos: son datos de consulta, no columnas para ordenar. --}}
                            <p class="text-xs text-muted">DNI {{ $paciente->dni_con_puntos }} · {{ $paciente->edad }} años</p>
                        </td>
                        {{-- Fecha de ingreso al espacio. Es opcional: raya si no está cargada. --}}
                        <td class="px-4 py-3 text-muted max-md:hidden">{{ $paciente->fecha_ingreso?->format('d/m/Y') ?? '—' }}</td>
                        {{-- Equipo: una etiqueta por profesional, con un puntito del color de cada disciplina suya. Raya si todavía no tiene. --}}
                        <td class="px-4 py-3 max-md:hidden">
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($paciente->profesionales as $miembro)
                                    <span class="flex items-center gap-1.5 rounded-full bg-brand-soft px-2.5 py-1 text-xs text-brand">
                                        @foreach ($miembro->disciplinas as $disciplina)
                                            <x-disciplina.punto :disciplina="$disciplina" />
                                        @endforeach
                                        {{ $miembro->name }}
                                    </span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 max-md:hidden">
                            @if ($paciente->activo)
                                <span class="rounded-full bg-accent/10 px-2.5 py-1 text-xs font-semibold text-accent">Activo</span>
                            @else
                                <span class="rounded-full bg-line px-2.5 py-1 text-xs font-semibold text-muted">Inactivo</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="border-t border-line px-4 py-6 text-center text-muted">No hay pacientes para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
