{{-- Vista de App\Livewire\Inicio: saludo, tarjetas de resumen y la tabla "Mis pacientes". --}}
<div class="grid gap-4">
    <h1 class="text-[22px] font-bold">Hola, {{ auth()->user()->name }} 👋</h1>

    {{-- Tarjetas de resumen: un número grande y debajo qué cuenta (ver 'resumen' en la clase). --}}
    <div class="grid grid-cols-3 gap-4">
        @foreach ($resumen as $texto => $numero)
            <div class="rounded-[20px] bg-white px-6 py-5 shadow-[0_10px_40px] shadow-brand/15">
                <p class="text-[28px] font-bold leading-tight text-brand">{{ $numero }}</p>
                <p class="text-sm text-muted">{{ $texto }}</p>
            </div>
        @endforeach
    </div>

    {{-- Mis pacientes --}}
    <div class="grid gap-2 rounded-[20px] bg-white p-2 shadow-[0_10px_40px] shadow-brand/15">
        {{-- Título, buscador y filtros. wire:model.live vuelve a armar la tabla apenas cambian. --}}
        <div class="flex flex-wrap items-center gap-3 px-4 pt-4">
            <h2 class="mr-auto font-semibold">Mis pacientes</h2>

            {{-- debounce: espera a que se deje de escribir un instante, para no consultar en cada tecla. --}}
            <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar paciente…"
                class="w-56 rounded-xl border-[1.5px] border-line px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">

            <select wire:model.live="estado"
                class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                <option value="activos">Activos</option>
                <option value="inactivos">Inactivos</option>
                <option value="todos">Todos</option>
            </select>

            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" wire:model.live="soloSinLeer" class="size-4 rounded border-line text-brand">
                Solo con mensajes sin leer
            </label>
        </div>

        <table class="w-full text-left text-sm">
            <thead class="text-muted">
                <tr>
                    <x-tabla.columna columna="paciente" :$orden :$ascendente>Paciente</x-tabla.columna>
                    <x-tabla.columna columna="edad" :$orden :$ascendente>Edad</x-tabla.columna>
                    <x-tabla.columna columna="actividad" :$orden :$ascendente>Última actividad</x-tabla.columna>
                    <th class="px-4 py-3 font-medium">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pacientes as $paciente)
                    {{-- Toda la fila lleva a la ficha (onclick); el nombre además es un link común. Al abrirla, sus mensajes pasan a leídos. --}}
                    <tr wire:key="paciente-{{ $paciente->id }}" onclick="window.location = '{{ route('pacientes.ver', $paciente) }}'"
                        class="cursor-pointer border-t border-line transition-colors hover:bg-brand-soft/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('pacientes.ver', $paciente) }}" class="font-semibold text-brand hover:text-brand-dark">{{ $paciente->nombre_completo }}</a>
                            @if ($sinLeer->has($paciente->id))
                                <span class="ml-1 rounded-full bg-brand-soft px-2 py-0.5 text-xs text-brand">{{ $sinLeer[$paciente->id] }} {{ $sinLeer[$paciente->id] == 1 ? 'mensaje' : 'mensajes' }} sin leer</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $paciente->edad }} años</td>
                        {{-- Fecha del último mensaje y quién lo escribió (nunca el texto). Raya si todavía no hay ninguno. --}}
                        <td class="px-4 py-3 text-muted">
                            @if ($paciente->ultimoMensaje)
                                {{ $paciente->ultimoMensaje->created_at->format('d/m/Y') }} · {{ $paciente->ultimoMensaje->autor->name }}
                            @else
                                —
                            @endif
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
                        <td colspan="4" class="border-t border-line px-4 py-6 text-center text-muted">
                            {{ $tienePacientes ? 'Ningún paciente coincide con la búsqueda o los filtros.' : 'Todavía no tenés pacientes asignados.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
