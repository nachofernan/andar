{{-- Vista de App\Livewire\Usuarios\Lista: tabla de disciplinas a la izquierda (angosta) y tabla de profesionales a la derecha. --}}
{{--
    Mobile (max-md:, ver docs/mobile.md): un bloque debajo del otro, de borde a borde y sin forma de tarjeta (sin esquinas
    redondeadas ni sombra; los márgenes negativos les comen el margen que deja la página). Arriba Disciplinas, plegada:
    solo se ve su título hasta tocar "Ver disciplinas".
--}}
<div class="grid grid-cols-[24rem_1fr] items-start gap-8 max-md:grid-cols-1 max-md:gap-3">
    {{-- Disciplinas: tabla angosta. Clic en una fila lleva a su pantalla, donde se ve y se edita. --}}
    {{-- "abierta" (Alpine) dice si en mobile se ve la tabla; en desktop no cambia nada. --}}
    <div class="grid gap-4" x-data="{ abierta: false }">
        <div class="grid gap-2 rounded-[20px] bg-white p-2 shadow-[0_10px_40px] shadow-brand/15 max-md:-mx-3 max-md:-mt-3 max-md:rounded-none max-md:shadow-none">
            {{-- Cabecera de la tarjeta: título con la cantidad y, a la derecha, el link para cargar una nueva. --}}
            <div class="flex items-center justify-between gap-3 px-4 pt-4">
                <h2 class="flex items-center gap-2 font-semibold">
                    Disciplinas
                    <span class="rounded-full bg-brand-soft px-3 py-1 text-sm font-semibold text-brand">{{ $disciplinas->count() }}</span>
                </h2>
                <a href="{{ route('disciplinas.crear') }}" class="font-semibold text-brand hover:underline">+ Nueva</a>
            </div>

            {{-- Solo mobile: el link que muestra u oculta la tabla de abajo. --}}
            <button type="button" @click="abierta = ! abierta" class="cursor-pointer px-4 pb-2 text-left text-sm font-semibold text-brand md:hidden"
                x-text="abierta ? 'Ocultar disciplinas ▲' : 'Ver disciplinas ▼'">Ver disciplinas ▼</button>

            {{-- En mobile arranca oculta (max-md:hidden) y Alpine le saca esa clase cuando "abierta" es true. --}}
            <table class="w-full text-left text-sm max-md:hidden" :class="{ 'max-md:hidden': ! abierta }">
                <thead class="text-muted">
                    <tr>
                        <th class="px-4 py-3 font-medium">Disciplina</th>
                        <th class="px-4 py-3 text-right font-medium">Profesionales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($disciplinas as $item)
                        {{-- Toda la fila lleva a editar la disciplina; el nombre además es un link común. --}}
                        <tr wire:key="disciplina-{{ $item->id }}" onclick="window.location = '{{ route('disciplinas.editar', $item) }}'"
                            class="cursor-pointer border-t border-line transition-colors hover:bg-brand-soft/50">
                            <td class="px-4 py-3">
                                {{-- Circulito con el color de la disciplina, y su nombre. --}}
                                <a href="{{ route('disciplinas.editar', $item) }}" class="flex items-center gap-2.5 font-semibold text-brand hover:text-brand-dark">
                                    <x-disciplina.punto :disciplina="$item" class="size-2.5" />
                                    {{ $item->nombre }}
                                </a>
                            </td>
                            {{-- Cuántos profesionales la tienen. --}}
                            <td class="px-4 py-3 text-right text-muted">{{ $item->profesionales_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="border-t border-line px-4 py-6 text-center text-muted">Todavía no hay disciplinas cargadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Profesionales --}}
    <div class="grid gap-4">
        <div class="grid gap-2 rounded-[20px] bg-white p-2 shadow-[0_10px_40px] shadow-brand/15 max-md:-mx-3 max-md:-mb-3 max-md:rounded-none max-md:shadow-none">
            {{-- Cabecera de la tarjeta: título con la cantidad y, a la derecha, el link para cargar uno nuevo. --}}
            <div class="flex items-center justify-between gap-3 px-4 pt-4">
                <h1 class="flex items-center gap-2 font-semibold">
                    Profesionales
                    {{-- Cuántos hay en la tabla en este momento (cambia con el buscador y el filtro). --}}
                    <span class="rounded-full bg-brand-soft px-3 py-1 text-sm font-semibold text-brand">{{ $usuarios->count() }}</span>
                </h1>
                {{-- Link simple (sin fondo), subrayado al pasar el mouse. --}}
                <a href="{{ route('usuarios.crear') }}" class="font-semibold text-brand hover:underline">+ Nuevo</a>
            </div>

            {{-- Buscador y filtro. wire:model.live vuelve a armar la tabla apenas cambian. --}}
            <div class="flex flex-wrap items-center gap-3 px-4 pt-2">
                {{-- debounce: espera a que se deje de escribir un instante, para no consultar en cada tecla. --}}
                {{-- Mobile (max-md:): buscador y filtro a todo el ancho, con letra de 16 px para que el iPhone no haga zoom. --}}
                <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o email…"
                    class="mr-auto w-72 rounded-xl border-[1.5px] border-line px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:w-full max-md:text-base">

                <select wire:model.live="disciplina"
                    class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:w-full max-md:text-base">
                    <option value="">Todas las disciplinas</option>
                    @foreach ($disciplinas as $opcion)
                        <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </div>

            {{-- En mobile se ve como lista: sin títulos y solo con la columna "Nombre" (ver docs/mobile.md). --}}
            <table class="w-full text-left text-sm">
                <thead class="text-muted max-md:hidden">
                    <tr>
                        <x-tabla.columna columna="nombre" :$orden :$ascendente>Nombre</x-tabla.columna>
                        <x-tabla.columna columna="disciplinas" :$orden :$ascendente>Disciplinas</x-tabla.columna>
                        <x-tabla.columna columna="pacientes" :$orden :$ascendente derecha>Pacientes</x-tabla.columna>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $usuario)
                        {{-- Toda la fila lleva a editar al profesional; el nombre además es un link común. --}}
                        <tr wire:key="usuario-{{ $usuario->id }}" onclick="window.location = '{{ route('usuarios.editar', $usuario) }}'"
                            class="cursor-pointer border-t border-line transition-colors hover:bg-brand-soft/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('usuarios.editar', $usuario) }}" class="font-semibold text-brand hover:text-brand-dark">{{ $usuario->name }}</a>
                                {{-- No hay columna de rol: quien es de Dirección lleva esta etiqueta y el resto son profesionales. --}}
                                @if ($usuario->esDireccion())
                                    <span class="ml-1 rounded-full bg-accent/10 px-2 py-0.5 text-xs font-semibold text-accent">Admin</span>
                                @endif
                                {{-- El correo va debajo del nombre, más chico. --}}
                                <p class="text-xs text-muted">{{ $usuario->email }}</p>
                                {{-- Solo mobile: sus disciplinas van acá abajo, porque la columna de al lado no se ve. --}}
                                @if ($usuario->disciplinas->isNotEmpty())
                                    <div class="mt-1.5 flex flex-wrap gap-1.5 md:hidden">
                                        @foreach ($usuario->disciplinas as $item)
                                            <x-disciplina.etiqueta :disciplina="$item" />
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            {{-- Disciplinas: una etiqueta por cada una, con su color, o una raya si no tiene ninguna. --}}
                            <td class="px-4 py-3 max-md:hidden">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($usuario->disciplinas as $item)
                                        <x-disciplina.etiqueta :disciplina="$item" />
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </div>
                            </td>
                            {{-- Cuántos pacientes atiende. --}}
                            <td class="px-4 py-3 text-right text-muted max-md:hidden">{{ $usuario->pacientes_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="border-t border-line px-4 py-6 text-center text-muted">No hay profesionales para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
