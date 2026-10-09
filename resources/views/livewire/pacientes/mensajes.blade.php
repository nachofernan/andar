{{-- Vista de App\Livewire\Pacientes\Mensajes: historial de mensajes del equipo, dentro de la ficha. --}}
<div class="grid gap-4 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
    <h2 class="font-semibold">Historial del equipo</h2>

    @if (! $esDelEquipo)
        <p class="text-sm text-muted">Solo el equipo de este paciente puede ver el historial.</p>
    @else
        {{-- Formulario para escribir un mensaje nuevo. --}}
        <form wire:submit="enviar" class="grid gap-2">
            <textarea wire:model="cuerpo" rows="3" placeholder="Escribir un mensaje al equipo…"
                class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15"></textarea>
            @error('cuerpo') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror

            {{-- Adjuntos. wire:key cambia después de cada envío para que el selector vuelva a quedar vacío. --}}
            <label class="grid gap-1.5 text-sm font-medium">Adjuntar archivos
                <span class="font-normal text-muted">(opcional: PDF, Word, Excel o imágenes; hasta {{ \App\Models\Mensaje::MAX_ADJUNTOS }} archivos de {{ \App\Models\Mensaje::MAX_KB_ADJUNTO / 1024 }} MB)</span>
                <input type="file" wire:model="archivos" wire:key="archivos-{{ $envios }}" multiple
                    class="text-sm font-normal text-muted file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-soft file:px-3 file:py-1.5 file:font-semibold file:text-brand">
            </label>
            {{-- Se muestra solo mientras los archivos se están subiendo. --}}
            <span wire:loading wire:target="archivos" class="text-[13px] text-muted">Subiendo archivos…</span>
            @error('archivos') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
            @error('archivos.*') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror

            <div class="flex flex-wrap items-center justify-between gap-2">
                <select wire:model="tipo" aria-label="Tipo de mensaje"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15">
                    @foreach ($tipos as $clave => $nombre)
                        <option value="{{ $clave }}">{{ $nombre }}</option>
                    @endforeach
                </select>
                {{-- El botón se desactiva mientras suben los archivos, para no enviar el mensaje sin ellos. --}}
                <button type="submit" wire:loading.attr="disabled" wire:target="archivos"
                    class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50">Enviar</button>
            </div>
            @error('tipo') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror
        </form>

        {{-- Archivos del paciente: todos los adjuntos de su historial, juntos. Solo aparece si hay alguno. --}}
        @if ($conArchivos->isNotEmpty())
            <div class="grid gap-2 border-t border-line pt-4 text-sm">
                <h3 class="font-semibold">Archivos del paciente</h3>
                @foreach ($conArchivos as $mensaje)
                    @foreach ($mensaje->getMedia('adjuntos') as $adjunto)
                        <div wire:key="archivo-{{ $adjunto->id }}" class="flex flex-wrap items-baseline gap-x-2">
                            <a href="{{ route('adjuntos.descargar', $adjunto) }}" class="font-semibold text-brand hover:text-brand-dark">📎 {{ \App\Models\Mensaje::nombreAdjunto($adjunto) }}</a>
                            <span class="text-xs text-muted">{{ \App\Models\Mensaje::tamanoAdjunto($adjunto) }} · {{ $mensaje->autor->name }} · {{ $mensaje->created_at->format('d/m/Y') }}</span>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif

        {{-- Filtro por tipo. El elegido queda pintado de violeta. --}}
        <div class="flex flex-wrap gap-2 border-t border-line pt-4">
            @foreach (['' => 'Todos'] + $tipos as $clave => $nombre)
                <button type="button" wire:click="$set('filtro', '{{ $clave }}')" wire:key="filtro-{{ $clave }}"
                    class="cursor-pointer rounded-full px-3 py-1 text-xs font-semibold transition-colors {{ $filtro === $clave ? 'bg-brand text-white' : 'bg-brand-soft text-brand hover:bg-[#e6dfff]' }}">
                    {{ $nombre }}
                </button>
            @endforeach
        </div>

        {{-- Mensajes, del más nuevo al más viejo. --}}
        @forelse ($mensajes as $mensaje)
            @php
                // "Nuevo" = lo escribió otra persona después de la última vez que abrí este historial.
                $esNuevo = $mensaje->user_id !== auth()->id()
                    && ($vistoHasta === null || $mensaje->created_at->gt($vistoHasta));
            @endphp
            <div wire:key="mensaje-{{ $mensaje->id }}" class="grid gap-1.5 rounded-xl border p-4 text-sm {{ $esNuevo ? 'border-brand bg-brand-soft/40' : 'border-line' }}">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">{{ $mensaje->autor->name }}</span>
                    {{-- Una sola etiqueta con todas las disciplinas de quien escribió, separadas por un punto. --}}
                    @if ($mensaje->autor->disciplinas->isNotEmpty())
                        <span class="rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold text-brand">{{ $mensaje->autor->disciplinas->pluck('nombre')->join(' · ') }}</span>
                    @endif
                    {{-- El tipo "Mensaje" es el común: no se etiqueta, para que resalten los demás. --}}
                    @if ($mensaje->tipo !== 'mensaje')
                        <span class="rounded-full bg-accent/10 px-2.5 py-1 text-xs font-semibold text-accent">{{ $tipos[$mensaje->tipo] ?? $mensaje->tipo }}</span>
                    @endif
                    @if ($esNuevo)
                        <span class="rounded-full bg-brand px-2.5 py-1 text-xs font-semibold text-white">Nuevo</span>
                    @endif
                    <span class="ml-auto text-xs text-muted">{{ $mensaje->created_at->format('d/m/Y H:i') }}</span>
                </div>
                {{-- whitespace-pre-line respeta los saltos de línea que se escribieron. --}}
                <p class="whitespace-pre-line">{{ $mensaje->cuerpo }}</p>

                {{-- Adjuntos del mensaje: cada uno es un link de descarga. --}}
                @foreach ($mensaje->getMedia('adjuntos') as $adjunto)
                    <a href="{{ route('adjuntos.descargar', $adjunto) }}" class="justify-self-start font-semibold text-brand hover:text-brand-dark">
                        📎 {{ \App\Models\Mensaje::nombreAdjunto($adjunto) }}
                        <span class="text-xs font-normal text-muted">({{ \App\Models\Mensaje::tamanoAdjunto($adjunto) }})</span>
                    </a>
                @endforeach
            </div>
        @empty
            <p class="text-sm text-muted">
                {{ $filtro === '' ? 'Todavía no hay mensajes.' : 'No hay mensajes de este tipo.' }}
            </p>
        @endforelse
    @endif
</div>
