{{--
    Una sección de un formulario: tarjeta blanca con título y, opcionalmente, una línea de ayuda debajo.

    Uso:
        <x-formulario.seccion titulo="Datos personales" ayuda="Quién es el paciente.">
            ...campos...
        </x-formulario.seccion>
--}}
@props(['titulo', 'ayuda' => null])

<section {{ $attributes->class('grid gap-4 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15') }}>
    <div>
        <h2 class="font-semibold">{{ $titulo }}</h2>
        @if ($ayuda)
            <p class="text-sm text-muted">{{ $ayuda }}</p>
        @endif
    </div>

    {{ $slot }}
</section>
