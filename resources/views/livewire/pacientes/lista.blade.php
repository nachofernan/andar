{{-- Vista de App\Livewire\Pacientes\Lista. --}}
<div class="grid gap-4">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-[22px] font-bold">Pacientes</h1>
        {{-- Solo Dirección carga pacientes. --}}
        @if (auth()->user()->esDireccion())
            <a href="{{ route('pacientes.crear') }}" class="rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Nuevo paciente</a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-[20px] bg-white p-2 shadow-[0_10px_40px] shadow-brand/15">
        <table class="w-full text-left text-sm">
            <thead class="text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Paciente</th>
                    <th class="px-4 py-3 font-medium">DNI</th>
                    <th class="px-4 py-3 font-medium">Edad</th>
                    <th class="px-4 py-3 font-medium">Equipo</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pacientes as $paciente)
                    <tr wire:key="paciente-{{ $paciente->id }}" class="border-t border-line">
                        <td class="px-4 py-3">
                            {{-- El nombre lleva a la ficha del paciente. --}}
                            <a href="{{ route('pacientes.ver', $paciente) }}" class="font-semibold text-brand hover:text-brand-dark">{{ $paciente->nombre_completo }}</a>
                            {{-- Globito con los mensajes sin leer de este paciente (solo si soy de su equipo y hay alguno). --}}
                            @if ($sinLeer->has($paciente->id))
                                <span class="ml-1 rounded-full bg-brand px-1.5 py-0.5 text-xs font-semibold text-white" title="Mensajes sin leer">{{ $sinLeer[$paciente->id] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $paciente->dni_con_puntos }}</td>
                        <td class="px-4 py-3 text-muted">{{ $paciente->edad }} años</td>
                        <td class="px-4 py-3 text-muted">{{ $paciente->profesionales->pluck('name')->join(', ') ?: '—' }}</td>
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
                        <td colspan="5" class="px-4 py-6 text-center text-muted">{{ auth()->user()->esDireccion() ? 'Todavía no hay pacientes cargados.' : 'Todavía no tenés pacientes asignados.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
