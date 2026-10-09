{{-- Vista de App\Livewire\Pacientes\Ficha: datos del paciente y su equipo. --}}
<div class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('pacientes') }}" class="text-sm font-medium text-muted hover:text-brand">← Volver a la lista</a>
            <h1 class="flex items-center gap-3 text-[22px] font-bold">
                {{ $paciente->nombre }} {{ $paciente->apellido }}
                @if ($paciente->activo)
                    <span class="rounded-full bg-accent/10 px-2.5 py-1 text-xs font-semibold text-accent">Activo</span>
                @else
                    <span class="rounded-full bg-line px-2.5 py-1 text-xs font-semibold text-muted">Inactivo</span>
                @endif
            </h1>
        </div>
        <a href="{{ route('pacientes.editar', $paciente) }}" class="rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Editar</a>
    </div>

    {{-- Datos. Los opcionales que están vacíos se muestran con una raya. --}}
    <div class="rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-muted">DNI</dt>
                <dd class="font-medium">{{ $paciente->dni_con_puntos }}</dd>
            </div>
            <div>
                <dt class="text-muted">Fecha de nacimiento</dt>
                <dd class="font-medium">{{ $paciente->fecha_nacimiento->format('d/m/Y') }} ({{ $paciente->edad }} años)</dd>
            </div>
            <div>
                <dt class="text-muted">Ingreso al espacio</dt>
                <dd class="font-medium">{{ $paciente->fecha_ingreso?->format('d/m/Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted">Obra social</dt>
                <dd class="font-medium">{{ $paciente->obra_social ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted">Escuela</dt>
                <dd class="font-medium">{{ $paciente->escuela ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted">Grado</dt>
                <dd class="font-medium">{{ $paciente->grado ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-3">
                <dt class="text-muted">Diagnóstico</dt>
                {{-- whitespace-pre-line respeta los saltos de línea que se escribieron. --}}
                <dd class="font-medium whitespace-pre-line">{{ $paciente->diagnostico ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Equipo --}}
    <div class="grid gap-3 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        <h2 class="font-semibold">Equipo</h2>

        @forelse ($paciente->profesionales as $profesional)
            <div class="flex items-center gap-3 text-sm">
                <span class="font-medium">{{ $profesional->name }}</span>
                @if ($profesional->disciplina)
                    <span class="rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold text-brand">{{ $profesional->disciplina->nombre }}</span>
                @endif
            </div>
        @empty
            <p class="text-sm text-muted">Todavía no tiene profesionales asignados.</p>
        @endforelse
    </div>
</div>
