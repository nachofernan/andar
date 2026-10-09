{{-- Vista de App\Livewire\Disciplinas: formulario arriba, lista abajo. --}}
<div class="grid gap-4">
    <h1 class="text-[22px] font-bold">Disciplinas</h1>

    {{-- Formulario: sirve para crear y para editar (ver $editandoId en la clase). --}}
    <form wire:submit="guardar" class="grid gap-3 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        <h2 class="font-semibold">{{ $editandoId ? 'Editar disciplina' : 'Nueva disciplina' }}</h2>

        <label class="grid gap-1.5 text-sm font-medium">Nombre
            <input type="text" wire:model="nombre"
                class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
            @error('nombre') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
        </label>

        <div class="flex gap-2">
            <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
            @if ($editandoId)
                <button type="button" wire:click="cancelar" class="cursor-pointer rounded-xl bg-brand-soft px-4 py-2.5 font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Cancelar</button>
            @endif
        </div>
    </form>

    {{-- Lista --}}
    <div class="overflow-x-auto rounded-[20px] bg-white p-2 shadow-[0_10px_40px] shadow-brand/15">
        <table class="w-full text-left text-sm">
            <thead class="text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="px-4 py-3 font-medium">Profesionales</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($disciplinas as $disciplina)
                    {{-- wire:key le dice a Livewire qué fila es cuál cuando la lista cambia. --}}
                    <tr wire:key="disciplina-{{ $disciplina->id }}" class="border-t border-line">
                        <td class="px-4 py-3 font-medium">{{ $disciplina->nombre }}</td>
                        <td class="px-4 py-3 text-muted">{{ $disciplina->profesionales_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="editar({{ $disciplina->id }})" class="cursor-pointer font-semibold text-brand hover:text-brand-dark">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-muted">Todavía no hay disciplinas cargadas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
