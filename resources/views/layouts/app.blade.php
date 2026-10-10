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
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                <a href="{{ route('inicio') }}" class="flex items-center gap-2">
                    <span class="grid size-8 place-items-center rounded-full bg-linear-to-br from-brand to-accent font-bold text-white">A</span>
                    <span class="text-lg font-bold text-brand">Andar</span>
                </a>

                {{-- Menú. El link de la zona actual se marca en violeta. --}}
                @php
                    // Total de mensajes sin leer del usuario, para el puntito rojo de "Inicio" (ver User::mensajesSinLeer).
                    $sinLeer = auth()->user()->mensajesSinLeer()->sum();
                    // ¿Estamos en alguna pantalla de administración? (todas cuelgan de /administracion)
                    $enAdministracion = request()->is('administracion*');
                @endphp
                <nav class="flex items-center gap-8 text-sm font-medium">
                    {{-- "Inicio" también queda marcado en la ficha de un paciente, porque se llega desde ahí. --}}
                    <a href="{{ route('inicio') }}" class="relative {{ request()->routeIs('inicio', 'pacientes.ver') ? 'text-brand' : 'text-muted hover:text-brand' }}">
                        Inicio
                        {{-- Puntito rojo arriba a la derecha si hay mensajes sin leer. No dice cuántos: el detalle está en el Inicio. --}}
                        @if ($sinLeer > 0)
                            <span class="absolute -right-2 -top-0.5 size-2 rounded-full bg-red-500" title="Tenés mensajes sin leer"></span>
                        @endif
                    </a>
                    {{--
                        Administración: solo para Dirección. Al hacer clic abre un menú chico con sus pantallas.
                        Abrir y cerrar lo maneja Alpine (viene incluido con Livewire): "abierto" es true o false.
                    --}}
                    @if (auth()->user()->esDireccion())
                        @php
                            // Texto del link => [ruta a la que lleva, rutas en las que queda marcado].
                            $administracion = [
                                'Pacientes' => ['pacientes', ['pacientes', 'pacientes.crear', 'pacientes.editar']],
                                'Profesionales y disciplinas' => ['usuarios', ['usuarios', 'usuarios.*', 'disciplinas.*']],
                            ];
                        @endphp
                        <div class="relative" x-data="{ abierto: false }" @click.outside="abierto = false">
                            <button type="button" @click="abierto = ! abierto"
                                class="flex cursor-pointer items-center gap-1.5 {{ $enAdministracion ? 'text-brand' : 'text-muted hover:text-brand' }}">
                                Administración
                                <span class="text-[10px]">▼</span>
                            </button>
                            {{-- Arranca oculto (display: none); Alpine lo muestra cuando "abierto" es true. --}}
                            <div x-show="abierto" style="display: none" class="absolute left-0 z-10 mt-2 w-60 rounded-xl border border-line bg-white p-1.5 font-normal text-ink shadow-lg">
                                @foreach ($administracion as $texto => [$ruta, $activaEn])
                                    <a href="{{ route($ruta) }}" class="block rounded-lg px-3 py-2 hover:bg-brand-soft {{ request()->routeIs(...$activaEn) ? 'font-semibold text-brand' : '' }}">{{ $texto }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </nav>

                {{--
                    Nombre del usuario: al hacer clic abre un menú chico con "Mi perfil" y "Salir".
                    Abrir y cerrar lo maneja Alpine (viene incluido con Livewire): "abierto" es true o false.
                --}}
                <div class="relative ml-auto text-sm" x-data="{ abierto: false }" @click.outside="abierto = false">
                    <button type="button" @click="abierto = ! abierto"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-colors hover:bg-brand-soft {{ request()->routeIs('perfil') ? 'text-brand' : '' }}">
                        {{ auth()->user()->name }}
                        <span class="text-[10px] text-muted">▼</span>
                    </button>
                    {{-- Arranca oculto (display: none); Alpine lo muestra cuando "abierto" es true. --}}
                    <div x-show="abierto" style="display: none" class="absolute right-0 z-10 mt-2 w-44 rounded-xl border border-line bg-white p-1.5 shadow-lg">
                        <a href="{{ route('perfil') }}" class="block rounded-lg px-3 py-2 hover:bg-brand-soft">Mi perfil</a>
                        {{-- Salir es un POST (no un link) para que no se pueda disparar desde afuera. --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full cursor-pointer rounded-lg px-3 py-2 text-left hover:bg-brand-soft">Salir</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl p-4">
            {{ $slot }}
        </main>
    @else
        {{-- Sin sesión (login): la pantalla se acomoda sola. --}}
        {{ $slot }}
    @endauth
</body>
</html>
