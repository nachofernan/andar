{{--
    Vista de App\Livewire\Pacientes\Ficha.
    Arriba el link para volver y la cabecera del paciente; debajo, dos columnas: el historial (ancho) y, al costado, equipo, diagnóstico y archivos.
--}}
<div class="grid gap-4">
    {{-- Link para volver a "Mis pacientes" (el Inicio), que es desde donde se abre la ficha. --}}
    <div>
        <a href="{{ route('inicio') }}" class="text-sm font-medium text-muted hover:text-brand">← Volver a mis pacientes</a>
    </div>

    {{-- Cabecera: quién es y, a la derecha, los datos sueltos. Los opcionales vacíos se muestran con una raya. --}}
    <div class="flex flex-wrap items-start gap-x-6 gap-y-4 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
        {{-- Círculo con las iniciales del paciente. --}}
        <span class="grid size-14 shrink-0 place-items-center rounded-full bg-linear-to-br from-brand to-accent text-lg font-bold text-white">{{ $paciente->iniciales }}</span>

        <div class="min-w-0 flex-1">
            <h1 class="flex flex-wrap items-center gap-3 text-[22px] font-bold">
                {{ $paciente->nombre }} {{ $paciente->apellido }}
                @if ($paciente->activo)
                    <span class="rounded-full bg-accent/10 px-2.5 py-1 text-xs font-semibold text-accent">Activo</span>
                @else
                    <span class="rounded-full bg-line px-2.5 py-1 text-xs font-semibold text-muted">Inactivo</span>
                @endif
            </h1>
            {{-- DNI, edad y, si están cargados, grado y escuela, separados por un punto. --}}
            <p class="text-sm text-muted">
                {{ collect(['DNI '.$paciente->dni_con_puntos, $paciente->edad.' años', $paciente->grado, $paciente->escuela])->filter()->join(' · ') }}
            </p>
        </div>

        <dl class="flex gap-12 text-sm">
            <div>
                <dt class="text-xs text-muted">Nacimiento</dt>
                <dd class="font-medium">{{ $paciente->fecha_nacimiento->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Ingreso</dt>
                <dd class="font-medium">{{ $paciente->fecha_ingreso?->format('d/m/Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Obra social</dt>
                <dd class="font-medium">{{ $paciente->obra_social ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Las dos columnas miden lo mismo de alto: el historial se estira hasta igualar a la lateral (ver la vista de Mensajes). --}}
    <div class="grid grid-cols-[1fr_20rem] gap-4">
        {{-- Historial del equipo (el chat): es otro componente (App\Livewire\Pacientes\Mensajes). --}}
        <livewire:pacientes.mensajes :paciente="$paciente" />

        {{-- Columna lateral. content-start: las tarjetas quedan arriba, sin estirarse si el historial es más alto. --}}
        <aside class="grid content-start gap-4">
            {{-- Equipo: un renglón por profesional, con su círculo de iniciales y las etiquetas de color de sus disciplinas. --}}
            <div class="grid gap-3 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
                <h2 class="font-semibold">Equipo</h2>
                @forelse ($paciente->profesionales as $profesional)
                    <div wire:key="profesional-{{ $profesional->id }}" class="flex items-start gap-2.5 text-sm">
                        <x-avatar :usuario="$profesional" class="size-8 text-xs" />
                        <div class="grid gap-1">
                            <span class="font-medium">{{ $profesional->name }}</span>
                            @if ($profesional->disciplinas->isNotEmpty())
                                <span class="flex flex-wrap gap-1">
                                    @foreach ($profesional->disciplinas as $disciplina)
                                        <x-disciplina.etiqueta :disciplina="$disciplina" class="py-0.5" />
                                    @endforeach
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">Todavía no tiene profesionales asignados.</p>
                @endforelse
            </div>

            {{-- Diagnóstico. El equipo lo puede cambiar con "Editar", que abre una ventana (ver más abajo). --}}
            <div class="grid gap-3 rounded-[20px] bg-white p-6 shadow-[0_10px_40px] shadow-brand/15">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold">Diagnóstico</h2>
                    @if ($esDelEquipo)
                        <button type="button" wire:click="editarDiagnostico" class="cursor-pointer text-sm font-semibold text-brand hover:underline">Editar</button>
                    @endif
                </div>

                {{-- whitespace-pre-line respeta los saltos de línea que se escribieron. --}}
                <p class="whitespace-pre-line text-sm {{ $paciente->diagnostico ? '' : 'text-muted' }}">{{ $paciente->diagnostico ?? 'Sin diagnóstico cargado.' }}</p>
            </div>

            {{-- Archivos: todos los adjuntos del historial, juntos. Solo para el equipo, y solo si hay alguno. --}}
            @if ($conArchivos->isNotEmpty())
                <div class="grid gap-3 rounded-[20px] bg-white p-6 text-sm shadow-[0_10px_40px] shadow-brand/15">
                    <h2 class="text-base font-semibold">Archivos</h2>
                    {{-- La lista tiene un alto máximo: pasado ese punto, scrollea por dentro en vez de estirar la tarjeta. --}}
                    <div class="-mr-3 grid max-h-72 gap-3 overflow-y-auto pr-3">
                    @foreach ($conArchivos as $mensaje)
                        @foreach ($mensaje->getMedia('adjuntos') as $adjunto)
                            <div wire:key="archivo-{{ $adjunto->id }}" class="grid gap-0.5">
                                <a href="{{ route('adjuntos.descargar', $adjunto) }}" class="break-all font-semibold text-brand hover:text-brand-dark">📎 {{ \App\Models\Mensaje::nombreAdjunto($adjunto) }}</a>
                                <span class="text-xs text-muted">{{ \App\Models\Mensaje::tamanoAdjunto($adjunto) }} · {{ $mensaje->autor->name }} · {{ $mensaje->created_at->format('d/m/Y') }}</span>
                            </div>
                        @endforeach
                    @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </div>

    {{-- Ventana para cambiar el diagnóstico. Se dibuja solo mientras $editandoDiagnostico es true (ver la clase). --}}
    @if ($editandoDiagnostico)
        <x-modal :titulo="'Diagnóstico de '.$paciente->nombre.' '.$paciente->apellido" cerrar="cancelarDiagnostico">
            <form wire:submit="guardarDiagnostico" class="grid gap-3">
                {{-- x-init: apenas se abre la ventana, el cursor queda en el cuadro de texto. --}}
                <textarea wire:model="diagnostico" rows="12" aria-label="Diagnóstico" x-data x-init="$nextTick(() => $el.focus())"
                    class="rounded-xl border-[1.5px] border-line px-3.5 py-[11px] text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15"></textarea>
                @error('diagnostico') <span class="text-[13px] text-red-600">{{ $message }}</span> @enderror

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-muted">El cambio queda anotado en el historial, con lo que decía antes.</p>
                    <div class="flex gap-2">
                        <button type="button" wire:click="cancelarDiagnostico" class="cursor-pointer rounded-xl bg-brand-soft px-4 py-2.5 font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Cancelar</button>
                        <button type="submit" class="cursor-pointer rounded-xl bg-brand px-4 py-2.5 font-semibold text-white transition-colors hover:bg-brand-dark">Guardar</button>
                    </div>
                </div>
            </form>
        </x-modal>
    @endif
</div>
