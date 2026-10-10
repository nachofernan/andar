{{--
    Circulito del color de una disciplina. Al pasar el mouse muestra su nombre.
    El color viene de la base, por eso va en style="" y no como clase de Tailwind (ver docs/estilos.md).

    Uso: <x-disciplina.punto :disciplina="$disciplina" />
--}}
@props(['disciplina'])

<span {{ $attributes->class('inline-block size-2 shrink-0 rounded-full') }}
    style="background-color: {{ $disciplina->color }}" title="{{ $disciplina->nombre }}"></span>
