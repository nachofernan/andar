{{-- Vista de App\Livewire\Perfil: datos propios y cambio de contraseña (opcional). --}}
<div class="grid gap-4">
    <h1 class="text-[22px] font-bold">Mi perfil</h1>

    <form wire:submit="guardar" class="grid gap-5 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        @if ($guardado)
            <p class="rounded-xl bg-accent/10 px-3.5 py-2.5 text-sm font-semibold text-accent">Datos actualizados.</p>
        @endif

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

            <label class="grid content-start gap-1.5 text-sm font-medium">Teléfono / WhatsApp
                <input type="text" wire:model="telefono"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                @error('telefono') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        {{-- Cambio de contraseña: opcional, dejar en blanco para no cambiarla. --}}
        <fieldset class="grid gap-3 border-t border-line pt-4">
            <legend class="mb-1 text-sm font-semibold">Cambiar contraseña <span class="font-normal text-muted">(dejá estos campos en blanco para no cambiarla)</span></legend>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="grid content-start gap-1.5 text-sm font-medium">Contraseña actual
                    <input type="password" wire:model="contrasena_actual"
                        class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                    @error('contrasena_actual') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="grid content-start gap-1.5 text-sm font-medium">Contraseña nueva
                    <input type="password" wire:model="contrasena_nueva"
                        class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                    @error('contrasena_nueva') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="grid content-start gap-1.5 text-sm font-medium">Repetir contraseña nueva
                    <input type="password" wire:model="contrasena_nueva_confirmation"
                        class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                </label>
            </div>
        </fieldset>

        <div>
            <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
        </div>
    </form>
</div>
