{{-- Vista de App\Livewire\Pacientes\Formulario: sirve para crear y para editar (ver $pacienteId en la clase). --}}
<div class="grid gap-4">
    <h1 class="text-[22px] font-bold">{{ $pacienteId ? 'Editar paciente' : 'Nuevo paciente' }}</h1>

    <form wire:submit="guardar" class="grid gap-4 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid content-start gap-1.5 text-sm font-medium">Nombre
                <input type="text" wire:model="nombre"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('nombre') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">Apellido
                <input type="text" wire:model="apellido"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('apellido') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <label class="grid content-start gap-1.5 text-sm font-medium">DNI
                <input type="text" wire:model="dni" inputmode="numeric"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('dni') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">Fecha de nacimiento
                <input type="date" wire:model="fecha_nacimiento"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('fecha_nacimiento') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">Ingreso al espacio <span class="font-normal text-muted">(opcional)</span>
                <input type="date" wire:model="fecha_ingreso"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('fecha_ingreso') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <label class="grid content-start gap-1.5 text-sm font-medium">Obra social <span class="font-normal text-muted">(opcional)</span>
                <input type="text" wire:model="obra_social"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('obra_social') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">Escuela <span class="font-normal text-muted">(opcional)</span>
                <input type="text" wire:model="escuela"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('escuela') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">Grado <span class="font-normal text-muted">(opcional)</span>
                <input type="text" wire:model="grado"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('grado') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="grid gap-1.5 text-sm font-medium">Diagnóstico <span class="font-normal text-muted">(opcional)</span>
            <textarea wire:model="diagnostico" rows="4"
                class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15"></textarea>
            @error('diagnostico') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
        </label>

        {{-- Equipo: una casilla por usuario. Los tildados quedan como profesionales del paciente. --}}
        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">Equipo <span class="font-normal text-muted">(profesionales que lo atienden)</span></legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($usuarios as $usuario)
                    <label wire:key="usuario-{{ $usuario->id }}" class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="equipo" value="{{ $usuario->id }}">
                        {{ $usuario->name }}
                        @if ($usuario->disciplinas->isNotEmpty())
                            <span class="text-muted">· {{ $usuario->disciplinas->pluck('nombre')->join(', ') }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            @error('equipo.*') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
        </fieldset>

        {{-- Dar de baja: solo al editar. Un paciente nuevo siempre nace activo. --}}
        @if ($pacienteId)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="activo">
                Activo <span class="text-muted">(destildar para darlo de baja sin borrar sus datos)</span>
            </label>
        @endif

        <div class="flex gap-2">
            <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
            {{-- Cancelar vuelve a la ficha (editando) o a la lista (creando). --}}
            <a href="{{ $pacienteId ? route('pacientes.ver', $pacienteId) : route('pacientes') }}" class="rounded-xl bg-brand-soft px-4 py-2.5 font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Cancelar</a>
        </div>
    </form>
</div>
