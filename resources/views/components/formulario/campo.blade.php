{{--
    Un campo de formulario: su nombre arriba, el control (input, select, textarea) y, si lo hay, el error debajo.
    El "(opcional)" va en la misma línea que el nombre.

    - nombre:   el texto que se muestra arriba.
    - opcional: si se pone, agrega "(opcional)" al lado del nombre.
    - error:    el nombre de la propiedad del componente, para mostrar su mensaje de validación.

    Uso:
        <x-formulario.campo nombre="Obra social" opcional error="obra_social">
            <input type="text" wire:model="obra_social" class="...">
        </x-formulario.campo>
--}}
@props(['nombre', 'opcional' => false, 'error' => null])

<label {{ $attributes->class('grid content-start gap-1.5 text-sm font-medium') }}>
    <span>
        {{ $nombre }}
        @if ($opcional)
            <span class="font-normal text-muted">(opcional)</span>
        @endif
    </span>

    {{ $slot }}

    @if ($error)
        @error($error) <span class="text-[13px] font-normal text-red-600">{{ $message }}</span> @enderror
    @endif
</label>
