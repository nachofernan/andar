{{--
    Etiqueta de una disciplina, pintada con su color: la letra del color y el fondo del mismo color muy clarito.

    El color viene de la base, por eso va en style="" y no como clase de Tailwind (ver docs/estilos.md).
    El "1a" pegado al hexa es la transparencia del fondo (10 %).

    Uso: <x-disciplina.etiqueta :disciplina="$disciplina" />
--}}
@props(['disciplina'])

<span {{ $attributes->class('rounded-full px-2.5 py-1 text-xs font-medium') }}
    style="color: {{ $disciplina->color }}; background-color: {{ $disciplina->color }}1a">{{ $disciplina->nombre }}</span>
