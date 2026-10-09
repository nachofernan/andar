{{-- Vista de App\Livewire\Usuarios: formulario arriba, lista abajo. --}}
<div class="grid gap-4">
    <h1 class="text-[22px] font-bold">Usuarios</h1>

    {{-- Formulario: sirve para crear y para editar (ver $editandoId en la clase). --}}
    <form wire:submit="guardar" class="grid gap-3 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        <h2 class="font-semibold">{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>

        <div class="grid gap-3 sm:grid-cols-3">
            <label class="grid content-start gap-1.5 text-sm font-medium">Nombre
                <input type="text" wire:model="name"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('name') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">Email
                <input type="email" wire:model="email"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('email') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">Disciplina
                <select wire:model="disciplina_id"
                    class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                    <option value="">Sin disciplina</option>
                    @foreach ($disciplinas as $disciplina)
                        <option value="{{ $disciplina->id }}">{{ $disciplina->nombre }}</option>
                    @endforeach
                </select>
                @error('disciplina_id') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        @unless ($editandoId)
            <p class="text-sm text-muted">El usuario nuevo entra con la contraseña <b>{{ \App\Livewire\Usuarios::CONTRASENA_INICIAL }}</b>.</p>
        @endunless

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
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Disciplina</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($usuarios as $usuario)
                    {{-- wire:key le dice a Livewire qué fila es cuál cuando la lista cambia. --}}
                    <tr wire:key="usuario-{{ $usuario->id }}" class="border-t border-line">
                        <td class="px-4 py-3 font-medium">{{ $usuario->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $usuario->email }}</td>
                        <td class="px-4 py-3 text-muted">{{ $usuario->disciplina?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="editar({{ $usuario->id }})" class="cursor-pointer font-semibold text-brand hover:text-brand-dark">Editar</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
