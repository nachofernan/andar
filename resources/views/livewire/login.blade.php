{{-- Vista de App\Livewire\Login. wire:submit llama al método entrar() de la clase. --}}
{{-- El div de afuera centra la tarjeta en la pantalla. --}}
{{--
    Mobile (max-md:min-h-dvh): "screen" mide la pantalla entera, incluso la parte que tapa la barra del navegador
    del celular, y por eso sobraba un poquito de scroll. "dvh" mide solo lo que se ve.
--}}
<div class="grid min-h-screen place-items-center p-4 max-md:min-h-dvh">
    <form wire:submit="entrar" class="grid w-full max-w-[380px] gap-4 rounded-[20px] bg-white px-8 py-9 max-md:px-6 shadow-[0_10px_40px] shadow-brand/15">
        {{-- Logo: círculo con la "A" + nombre --}}
        <div class="mb-1 flex items-center gap-3">
            <div class="grid size-11 place-items-center rounded-full bg-linear-to-br from-brand to-accent text-[22px] font-bold text-white">A</div>
            <div class="text-[26px] font-bold text-brand">Andar</div>
        </div>

        <p class="text-sm text-muted">Ingresá con tu cuenta para continuar.</p>

        <label class="grid gap-1.5 text-sm font-medium">Email
            <input type="email" wire:model="email" autofocus
                class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:text-base">
            @error('email') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="grid gap-1.5 text-sm font-medium">Contraseña
            <input type="password" wire:model="password"
                class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:text-base">
            @error('password') <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" wire:model="recordarme"> Recordarme</label>

        <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-3 font-semibold text-white transition-colors hover:bg-brand-dark">Entrar</button>
    </form>
</div>
