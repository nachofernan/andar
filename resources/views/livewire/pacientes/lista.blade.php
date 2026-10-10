{{-- Vista de App\Livewire\Pacientes\Lista: listado administrativo, con buscador, filtros y orden. --}}
<div class="grid gap-4">
    <div class="flex items-center justify-between gap-4">
        <h1 class="flex items-center gap-2 text-[22px] font-bold">
            Pacientes
            {{-- Cuántos hay en la tabla en este momento (cambia con el buscador y los filtros). --}}
            <span class="rounded-full bg-brand-soft px-3 py-1 text-sm font-semibold text-brand">{{ $pacientes->count() }} {{ $pacientes->count() === 1 ? 'paciente' : 'pacientes' }}</span>
        </h1>
        <a href="{{ route('pacientes.crear') }}" class="rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Nuevo paciente</a>
    </div>

    <div class="grid gap-2 rounded-[20px] bg-white p-2 shadow-[0_10px_40px] shadow-brand/15">
        {{-- Buscador y filtros. wire:model.live vuelve a armar la tabla apenas cambian. --}}
        <div class="flex flex-wrap items-center gap-3 px-4 pt-4">
            {{-- debounce: espera a que se deje de escribir un instante, para no consultar en cada tecla. --}}
            <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o DNI…"
                class="mr-auto w-72 rounded-xl border-[1.5px] border-line px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">

            <select wire:model.live="profesional"
                class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                <option value="">Todos los profesionales</option>
                @foreach ($profesionales as $usuario)
                    <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="estado"
                class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                <option value="activos">Activos</option>
                <option value="inactivos">Inactivos</option>
                <option value="todos">Todos los estados</option>
            </select>
        </div>

        <table class="w-full text-left text-sm">
            <thead class="text-muted">
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
                            {{-- DNI y edad van juntos debajo del nombre, más chicos: son datos de consulta, no columnas para ordenar. --}}
                            <p class="text-xs text-muted">DNI {{ $paciente->dni_con_puntos }} · {{ $paciente->edad }} años</p>
                        </td>
                        {{-- Fecha de ingreso al espacio. Es opcional: raya si no está cargada. --}}
                        <td class="px-4 py-3 text-muted">{{ $paciente->fecha_ingreso?->format('d/m/Y') ?? '—' }}</td>
                        {{-- Equipo: una etiqueta por profesional, con un puntito del color de cada disciplina suya. Raya si todavía no tiene. --}}
                        <td class="px-4 py-3">
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
                        <td class="px-4 py-3">
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
