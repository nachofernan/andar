{{--
    Vista de App\Livewire\Pacientes\Mensajes: el historial del equipo con forma de chat, dentro de la ficha.
    De arriba hacia abajo: los filtros, los mensajes (con scroll propio) y el cuadro para escribir.

    En mobile (clases max-md: y md:hidden, ver docs/mobile.md) los filtros quedan ocultos detrás de "Filtrar historial".
    Si están a la vista o no lo guarda Alpine en "filtros" (x-data, acá abajo); en desktop esa variable no cambia nada.
--}}
{{-- flex-col: los tres bloques van apilados y el del medio (los mensajes) se estira para ocupar el alto que sobre. --}}
{{--
    Mobile: deja de ser una tarjeta y va de borde a borde, para aprovechar todo el ancho: sin esquinas redondeadas ni
    sombra, y con márgenes negativos (-mx-3 -mb-3) que le comen el margen que deja la página a los costados y abajo.
--}}
<div class="flex flex-col rounded-[20px] bg-white shadow-[0_10px_40px] shadow-brand/15 max-md:-mx-3 max-md:-mb-3 max-md:rounded-none max-md:shadow-none" x-data="{ filtros: false }">
    @if (! $esDelEquipo)
        <div class="grid gap-2 p-6">
            <h2 class="font-semibold">Historial del equipo</h2>
            <p class="text-sm text-muted">Solo el equipo de este paciente puede ver el historial.</p>
        </div>
    @else
        {{-- Arriba, los filtros: por fechas (desde / hasta) y por tipo. wire:model.live vuelve a armar la lista apenas cambian. --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-line px-6 py-4 max-md:px-4 max-md:py-3">
            {{-- Solo mobile: el link que muestra u oculta los tres filtros de abajo. --}}
            <button type="button" @click="filtros = ! filtros" class="mr-auto cursor-pointer text-sm font-semibold text-brand md:hidden"
                x-text="filtros ? 'Ocultar filtros ▲' : 'Filtrar historial ▼'">Filtrar historial ▼</button>

            {{-- Fechas: se puede cargar una sola (solo "desde" o solo "hasta") o las dos. --}}
            {{-- En mobile los tres filtros arrancan ocultos (max-md:hidden) y Alpine les saca esa clase cuando "filtros" es true. --}}
            <label class="flex items-center gap-2 text-xs text-muted max-md:hidden max-md:w-full" :class="{ 'max-md:hidden': ! filtros }">Desde
                <input type="date" wire:model.live="desde" max="{{ $hasta }}"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-2 text-sm text-ink outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:flex-1 max-md:text-base">
            </label>
            <label class="flex items-center gap-2 text-xs text-muted max-md:hidden max-md:w-full" :class="{ 'max-md:hidden': ! filtros }">hasta
                <input type="date" wire:model.live="hasta" min="{{ $desde }}"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-2 text-sm text-ink outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:flex-1 max-md:text-base">
            </label>

            <select wire:model.live="filtro" aria-label="Mostrar" :class="{ 'max-md:hidden': ! filtros }"
                class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:hidden max-md:w-full max-md:text-base">
                <option value="">Todos</option>
                @foreach ($tipos as $clave => $nombre)
                    <option value="{{ $clave }}">{{ $nombre }}</option>
                @endforeach
            </select>

            {{-- Vuelve a mostrar todo. Solo aparece si hay algún filtro puesto. --}}
            @if ($hayFiltro)
                <button type="button" wire:click="borrarFiltros" class="cursor-pointer text-sm font-semibold text-brand hover:underline">Borrar filtros</button>
            @endif
        </div>

        {{--
            Los mensajes, del más viejo (arriba) al más nuevo (abajo), con scroll propio.
            Alto: como mínimo, lo que queda de pantalla (min-h): el alto de la ventana (100vh) menos unos 31rem, que es
            lo que ocupan la barra de arriba, el link de volver, la cabecera, los filtros y el cuadro de escribir. Así la
            ficha entra justa y la página no scrollea. En ventanas muy bajas no baja de 20rem.
            Si la columna de la derecha de la ficha es más alta que eso, esta zona crece hasta igualarla (flex-1),
            así el historial nunca queda más corto que el costado.
            basis-0 hace que su contenido no cuente para el alto: por largo que sea el historial, scrollea adentro.
            Alpine (x-data) lo deja parado en lo último al abrir la ficha y cada vez que se envía un mensaje,
            se anota un cambio o se tocan los filtros (los tres eventos que dispara Livewire). El pequeño retardo es para esperar
            a que el mensaje nuevo ya esté dibujado.
            En mobile (max-md:) la cuenta es otra, porque la cabecera es más baja y los filtros están plegados: el alto
            de la pantalla (100dvh, que descuenta la barra del navegador del celular) menos unos 30rem.
        --}}
        <div class="grid min-h-[max(20rem,calc(100vh-31rem))] flex-1 basis-0 content-start gap-4 overflow-y-auto px-6 py-4 max-md:min-h-[max(18rem,calc(100dvh-30rem))] max-md:gap-3 max-md:px-3"
            x-data="{ irAlFinal() { setTimeout(() => $el.scrollTop = $el.scrollHeight, 50) } }"
            x-init="irAlFinal()"
            x-on:mensaje-enviado.window="irAlFinal()"
            x-on:paciente-actualizado.window="irAlFinal()"
            x-on:ir-al-final.window="irAlFinal()">

            {{-- Agrupados por día, con un separador arriba de cada grupo. --}}
            @forelse ($mensajes->groupBy(fn ($mensaje) => $mensaje->created_at->format('Y-m-d')) as $dia => $delDia)
                @php
                    $fecha = \Illuminate\Support\Carbon::parse($dia);
                    // "hoy", "ayer" o la fecha escrita (con el año solo si no es el actual).
                    $separador = match (true) {
                        $fecha->isToday() => 'hoy',
                        $fecha->isYesterday() => 'ayer',
                        default => $fecha->locale('es')->isoFormat($fecha->isCurrentYear() ? 'dddd D [de] MMMM' : 'D [de] MMMM [de] YYYY'),
                    };
                @endphp
                <p wire:key="dia-{{ $dia }}" class="text-center text-xs text-muted">— {{ $separador }} —</p>

                @foreach ($delDia as $mensaje)
                    @php
                        $esMio = $mensaje->user_id === auth()->id();
                        // "Nuevo" = lo escribió otra persona después de la última vez que abrí este historial.
                        $esNuevo = ! $esMio && ($vistoHasta === null || $mensaje->created_at->gt($vistoHasta));
                    @endphp

                    @if ($mensaje->esActualizacion())
                        {{-- Entrada automática de la bitácora: un cambio en los datos del paciente. Sin globo, centrada y chica. --}}
                        <div wire:key="mensaje-{{ $mensaje->id }}" class="mx-auto grid w-full max-w-xl gap-1.5 rounded-xl px-4 py-3 text-xs {{ $esNuevo ? 'bg-brand-soft' : 'bg-line/40' }}">
                            <p class="text-muted">
                                <span class="mr-1 rounded-full bg-white px-2 py-0.5 font-semibold text-ink">Actualización</span>
                                <span class="font-semibold text-ink">{{ $mensaje->autor->name }}</span> cambió los datos · {{ $mensaje->created_at->format('H:i') }}
                                @if ($esNuevo)
                                    <span class="ml-1 rounded-full bg-brand px-2 py-0.5 font-semibold text-white">Nuevo</span>
                                @endif
                            </p>

                            @foreach ($mensaje->cambios as $cambio)
                                @if (isset($cambio['texto']))
                                    {{-- Cambios de equipo: vienen ya escritos ("Se sumó: ..."). --}}
                                    <p><span class="font-semibold">{{ $cambio['campo'] }}.</span> {{ $cambio['texto'] }}</p>
                                @elseif (mb_strlen(($cambio['antes'] ?? '').($cambio['ahora'] ?? '')) > 60)
                                    {{-- Textos largos (el diagnóstico): antes y ahora en renglones separados. --}}
                                    <div class="grid gap-0.5">
                                        <p class="font-semibold">{{ $cambio['campo'] }}</p>
                                        <p class="whitespace-pre-line text-muted"><span class="font-semibold">Antes:</span> {{ $cambio['antes'] ?? '—' }}</p>
                                        <p class="whitespace-pre-line"><span class="font-semibold">Ahora:</span> {{ $cambio['ahora'] ?? '—' }}</p>
                                    </div>
                                @else
                                    {{-- Textos cortos: en una línea, lo anterior tachado y lo nuevo al lado. --}}
                                    <p>
                                        <span class="font-semibold">{{ $cambio['campo'] }}:</span>
                                        <s class="text-muted">{{ $cambio['antes'] ?? '—' }}</s> → <span class="font-semibold">{{ $cambio['ahora'] ?? '—' }}</span>
                                    </p>
                                @endif
                            @endforeach
                        </div>
                    @else
                        {{-- Mensaje de una persona. Los míos van a la derecha (flex-row-reverse da vuelta el orden) y en violeta clarito. --}}
                        <div wire:key="mensaje-{{ $mensaje->id }}" class="flex items-start gap-3 max-md:gap-2 {{ $esMio ? 'flex-row-reverse' : '' }}">
                            {{-- Mobile: el círculo más chico y el globo más ancho, para aprovechar la pantalla. --}}
                            <x-avatar :usuario="$mensaje->autor" class="max-md:size-7 max-md:text-[11px]" />

                            <div class="grid max-w-[75%] max-md:max-w-[85%] gap-1 {{ $esMio ? 'justify-items-end' : 'justify-items-start' }}">
                                {{-- Quién, sus disciplinas y la hora. En mobile las disciplinas no se muestran (max-md:hidden), para ganar lugar. --}}
                                <div class="flex flex-wrap items-center gap-1.5 text-xs {{ $esMio ? 'flex-row-reverse' : '' }}">
                                    <span class="text-sm font-semibold">{{ $mensaje->autor->name }}</span>
                                    @foreach ($mensaje->autor->disciplinas as $disciplina)
                                        <x-disciplina.etiqueta :disciplina="$disciplina" class="py-0.5 max-md:hidden" />
                                    @endforeach
                                    <span class="text-muted">{{ $mensaje->created_at->format('H:i') }}</span>
                                    @if ($esNuevo)
                                        <span class="rounded-full bg-brand px-2 py-0.5 font-semibold text-white">Nuevo</span>
                                    @endif
                                </div>

                                {{-- El globo. La esquina del lado de quien escribe va menos redondeada. --}}
                                <div class="grid gap-1.5 rounded-2xl border px-4 py-2.5 text-sm {{ $esMio ? 'rounded-tr-md border-brand/20 bg-brand-soft' : 'rounded-tl-md border-line bg-white' }} {{ $esNuevo ? 'border-brand' : '' }}">
                                    {{-- El tipo "Mensaje" es el común: no se etiqueta, para que resalten los demás. --}}
                                    @if ($mensaje->tipo !== 'mensaje')
                                        <span class="justify-self-start rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-semibold text-accent">{{ $tipos[$mensaje->tipo] ?? $mensaje->tipo }}</span>
                                    @endif

                                    {{-- whitespace-pre-line respeta los saltos de línea que se escribieron. --}}
                                    <p class="whitespace-pre-line">{{ $mensaje->cuerpo }}</p>

                                    {{-- Adjuntos del mensaje: cada uno es un link de descarga. --}}
                                    @foreach ($mensaje->getMedia('adjuntos') as $adjunto)
                                        <a href="{{ route('adjuntos.descargar', $adjunto) }}" class="justify-self-start break-all font-semibold text-brand hover:text-brand-dark">
                                            📎 {{ \App\Models\Mensaje::nombreAdjunto($adjunto) }}
                                            <span class="text-xs font-normal text-muted">({{ \App\Models\Mensaje::tamanoAdjunto($adjunto) }})</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @empty
                <p class="text-sm text-muted">
                    {{ $hayFiltro ? 'No hay mensajes con esos filtros.' : 'Todavía no hay mensajes.' }}
                </p>
            @endforelse
        </div>

        {{-- Escribir un mensaje nuevo, al pie. --}}
        <form wire:submit="enviar" class="grid gap-2 border-t border-line px-6 py-4 max-md:p-3">
            {{-- Mobile: más bajo (max-md:h-20, unos dos renglones) y con letra de 16 px, para que el iPhone no haga zoom al tocarlo. --}}
            <textarea wire:model="cuerpo" rows="3" placeholder="Escribir un mensaje al equipo…"
                class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:h-20 max-md:text-base"></textarea>
            @error('cuerpo') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror

            {{--
                Mobile: los tres van siempre en un renglón (flex-nowrap). El tipo ocupa el lugar que sobre (flex-1),
                "Adjuntar" queda solo con el clip y "Enviar" un poco más angosto.
            --}}
            <div class="flex flex-wrap items-center gap-2 max-md:flex-nowrap">
                <select wire:model="tipo" aria-label="Tipo de mensaje"
                    class="rounded-xl border-[1.5px] border-line bg-white px-3.5 py-2 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15 max-md:min-w-0 max-md:flex-1 max-md:px-2.5 max-md:text-base">
                    @foreach ($tiposParaEscribir as $clave => $nombre)
                        <option value="{{ $clave }}">{{ $nombre }}</option>
                    @endforeach
                </select>

                {{--
                    Adjuntar: el selector de archivos real está oculto y se abre al hacer clic en este botón (es su <label>).
                    Cada elección se SUMA a las anteriores (ver updatedNuevos en la clase).
                    "accept" hace que el explorador muestre solo los tipos permitidos.
                    wire:key cambia con cada elección y cada envío, para que el selector quede vacío y se pueda volver a usar.
                --}}
                <label class="cursor-pointer rounded-xl bg-brand-soft px-3.5 py-2 text-sm font-semibold text-brand transition-colors hover:bg-[#e6dfff] max-md:shrink-0 max-md:text-base"
                    title="PDF, Word, Excel o imágenes. Hasta {{ \App\Models\Mensaje::MAX_ADJUNTOS }} archivos de {{ \App\Models\Mensaje::MAX_KB_ADJUNTO / 1024 }} MB.">
                    📎 <span class="max-md:hidden">Adjuntar</span>
                    <input type="file" wire:model="nuevos" wire:key="selector-{{ $envios }}-{{ count($archivos) }}" multiple class="hidden"
                        accept="{{ collect(\App\Models\Mensaje::EXTENSIONES_ADJUNTO)->map(fn ($extension) => '.'.$extension)->join(',') }}">
                </label>
                {{-- Se muestra solo mientras los archivos se están subiendo. --}}
                <span wire:loading wire:target="nuevos" class="text-[13px] text-muted max-md:shrink-0">Subiendo…</span>

                {{-- El botón se desactiva mientras suben los archivos, para no enviar el mensaje sin ellos. --}}
                <button type="submit" wire:loading.attr="disabled" wire:target="nuevos"
                    class="ml-auto cursor-pointer rounded-xl bg-brand px-5 py-2 font-semibold text-white transition-colors hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50 max-md:shrink-0 max-md:px-4">Enviar</button>
            </div>
            @error('tipo') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror

            {{-- Archivos ya elegidos: debajo de los botones, una etiqueta por cada uno, con una ✕ para sacarlo antes de enviar. --}}
            @if ($archivos !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach ($archivos as $posicion => $archivo)
                        <span wire:key="elegido-{{ $archivo->getFilename() }}" class="flex items-center gap-1.5 rounded-full bg-brand-soft py-1 pl-3 pr-1.5 text-xs text-brand">
                            📎 {{ $archivo->getClientOriginalName() }}
                            <button type="button" wire:click="quitarArchivo({{ $posicion }})" title="Quitar"
                                class="grid size-5 cursor-pointer place-items-center rounded-full font-semibold hover:bg-brand hover:text-white">✕</button>
                        </span>
                    @endforeach
                </div>
            @endif
            @error('archivos') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
            @error('archivos.*') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
        </form>
    @endif
</div>
