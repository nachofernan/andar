{{--
    Marco HTML de todas las pantallas: Livewire lo usa solo (ver config component_layout)
    y las vistas comunes lo usan con <x-layouts::app>.
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Andar</title>
    {{-- Carga el CSS (Tailwind) y el JS compilados por Vite. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Fondo con degradé violeta → verde agua. --}}
<body class="min-h-screen bg-linear-to-br from-[#efeaff] to-[#e4f6f6] bg-fixed font-sans text-ink">
    @auth
        {{-- Barra superior: solo con sesión iniciada. --}}
        <header class="border-b border-line bg-white">
            <div class="mx-auto flex max-w-4xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                <a href="{{ route('inicio') }}" class="flex items-center gap-2">
                    <span class="grid size-8 place-items-center rounded-full bg-linear-to-br from-brand to-accent font-bold text-white">A</span>
                    <span class="text-lg font-bold text-brand">Andar</span>
                </a>

                {{-- Menú. El link de la pantalla actual (o de sus subpáginas, ej. pacientes.ver) se marca en violeta. --}}
                @php
                    // Total de mensajes sin leer del usuario, para el globito de "Pacientes" (ver User::mensajesSinLeer).
                    $sinLeer = auth()->user()->mensajesSinLeer()->sum();
                @endphp
                <nav class="flex gap-4 text-sm font-medium">
                    @php
                        // Disciplinas y Usuarios son solo para Dirección: a un profesional no se le muestran.
                        $menu = ['inicio' => 'Inicio', 'pacientes' => 'Pacientes']
                            + (auth()->user()->esDireccion() ? ['disciplinas' => 'Disciplinas', 'usuarios' => 'Usuarios'] : []);
                    @endphp
                    @foreach ($menu as $ruta => $texto)
                        <a href="{{ route($ruta) }}" class="{{ request()->routeIs($ruta, $ruta.'.*') ? 'text-brand' : 'text-muted hover:text-brand' }}">
                            {{ $texto }}
                            {{-- Globito con la cantidad de mensajes sin leer. Si no hay ninguno, no aparece. --}}
                            @if ($ruta === 'pacientes' && $sinLeer > 0)
                                <span class="rounded-full bg-brand px-1.5 py-0.5 text-xs font-semibold text-white" title="Mensajes sin leer">{{ $sinLeer }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-3 text-sm">
                    <span class="text-muted">{{ auth()->user()->name }}</span>
                    {{-- Salir es un POST (no un link) para que no se pueda disparar desde afuera. --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="cursor-pointer rounded-lg bg-brand-soft px-3 py-1.5 font-semibold text-brand transition-colors hover:bg-[#e6dfff]">Salir</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-4xl p-4">
            {{ $slot }}
        </main>
    @else
        {{-- Sin sesión (login): la pantalla se acomoda sola. --}}
        {{ $slot }}
    @endauth
</body>
</html>
