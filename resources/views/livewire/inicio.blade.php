{{-- Vista de App\Livewire\Inicio: saludo y aviso de mensajes sin leer. --}}
<div class="grid gap-4">
    <div class="grid gap-2 rounded-[20px] bg-white px-8 py-9 shadow-[0_10px_40px] shadow-brand/15">
        <h1 class="text-[22px] font-bold">Hola, {{ auth()->user()->name }} 👋</h1>
        <p class="text-sm text-muted">Usá el menú de arriba para moverte.</p>
    </div>

    {{-- Mensajes sin leer: un renglón por paciente, con link a su ficha. Al abrir la ficha pasan a leídos. --}}
    <div class="grid gap-3 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        <h2 class="font-semibold">Mensajes sin leer</h2>

        @forelse ($pacientes as $paciente)
            <a href="{{ route('pacientes.ver', $paciente) }}" wire:key="paciente-{{ $paciente->id }}" class="flex items-center gap-2 text-sm">
                <span class="font-semibold text-brand hover:text-brand-dark">{{ $paciente->nombre_completo }}</span>
                <span class="rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-white">{{ $sinLeer[$paciente->id] }}</span>
            </a>
        @empty
            <p class="text-sm text-muted">No tenés mensajes sin leer.</p>
        @endforelse
    </div>
</div>
