{{--
    Círculo con las iniciales de un usuario, pintado con el color de su primera disciplina
    (o con el violeta de la app si no tiene ninguna).
    El color viene de la base, por eso va en style="" y no como clase de Tailwind (ver docs/estilos.md).

    Uso: <x-avatar :usuario="$mensaje->autor" />   (el usuario tiene que traer sus disciplinas cargadas)
--}}
@props(['usuario'])

<span {{ $attributes->class('grid size-9 shrink-0 place-items-center rounded-full text-[13px] font-semibold text-white') }}
    style="background-color: {{ $usuario->disciplinas->first()?->color ?? \App\Models\Disciplina::COLOR_INICIAL }}"
    title="{{ $usuario->name }}">{{ $usuario->iniciales }}</span>
