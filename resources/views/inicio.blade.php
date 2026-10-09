{{-- Página provisoria para quien ya inició sesión. Se reemplaza cuando haya pantallas reales. --}}
<x-layouts::app>
    <div class="grid gap-2 rounded-[20px] bg-white px-8 py-9 shadow-[0_10px_40px] shadow-brand/15">
        <h1 class="text-[22px] font-bold">Hola, {{ auth()->user()->name }} 👋</h1>
        <p class="text-sm text-muted">Ya iniciaste sesión. Usá el menú de arriba para moverte.</p>
    </div>
</x-layouts::app>
