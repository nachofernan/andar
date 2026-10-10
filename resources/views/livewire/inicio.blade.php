{{-- Vista de App\Livewire\Inicio: saludo y "mis pacientes", con el aviso de no leídos. --}}
<div class="grid gap-4">
    <div class="grid gap-2 rounded-[20px] bg-white px-8 py-9 shadow-[0_10px_40px] shadow-brand/15">
        <h1 class="text-[22px] font-bold">Hola, {{ auth()->user()->name }} 👋</h1>
        <p class="text-sm text-muted">Usá el menú de arriba para moverte.</p>
    </div>

    {{-- Mis pacientes: un renglón por paciente, con link a su ficha. Al abrirla, sus mensajes pasan a leídos. --}}
    <div class="grid gap-3 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold">Mis pacientes</h2>
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" wire:model.live="verInactivos" class="size-4 rounded border-line text-brand">
                Mostrar también los dados de baja
            </label>
        </div>

        @forelse ($pacientes as $paciente)
            <a href="{{ route('pacientes.ver', $paciente) }}" wire:key="paciente-{{ $paciente->id }}" class="flex items-center gap-2 text-sm">
                <span class="font-semibold text-brand hover:text-brand-dark">{{ $paciente->nombre_completo }}</span>
                @if (! $paciente->activo)
                    <span class="rounded-full bg-line px-2 py-0.5 text-xs font-semibold text-muted">Inactivo</span>
                @endif
                @if ($sinLeer->has($paciente->id))
                    <span class="rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-white" title="Mensajes sin leer">{{ $sinLeer[$paciente->id] }}</span>
                @endif
            </a>
        @empty
            <p class="text-sm text-muted">Todavía no tenés pacientes asignados.</p>
        @endforelse
    </div>
</div>
