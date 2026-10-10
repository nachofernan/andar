<?php

use App\Http\Controllers\AdjuntoController;
use App\Livewire\Disciplinas;
use App\Livewire\Inicio;
use App\Livewire\Login;
use App\Livewire\Pacientes;
use App\Livewire\Perfil;
use App\Livewire\Usuarios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Login: solo para quien NO inició sesión (si ya la tiene, lo manda al inicio).
Route::livewire('/login', Login::class)->name('login')->middleware('guest');

// Todo lo de acá adentro exige sesión iniciada (si no, redirige a /login).
Route::middleware('auth')->group(function () {
    Route::livewire('/', Inicio::class)->name('inicio');

    // Mi perfil: cualquier usuario con sesión cambia sus propios datos y contraseña.
    Route::livewire('/mi-perfil', Perfil::class)->name('perfil');

    // Solo para Dirección. El control no está acá sino en cada componente (use SoloDireccion; ver docs/roles.md).
    Route::livewire('/disciplinas', Disciplinas::class)->name('disciplinas');
    Route::livewire('/usuarios', Usuarios::class)->name('usuarios');

    // Pacientes. "crear" va antes que "{paciente}" para que no se confunda con un id.
    // El listado administrativo (y crear/editar) es solo de Dirección (ver SoloDireccion en el componente);
    // la ficha la puede ver cualquiera del equipo del paciente (se llega a ella desde el Inicio).
    Route::livewire('/pacientes', Pacientes\Lista::class)->name('pacientes');
    Route::livewire('/pacientes/crear', Pacientes\Formulario::class)->name('pacientes.crear');
    Route::livewire('/pacientes/{paciente}', Pacientes\Ficha::class)->name('pacientes.ver');
    Route::livewire('/pacientes/{paciente}/editar', Pacientes\Formulario::class)->name('pacientes.editar');

    // Descarga de un archivo adjunto a un mensaje. El control de quién puede bajarlo está en el controlador.
    Route::get('/adjuntos/{media}', AdjuntoController::class)->name('adjuntos.descargar');

    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();       // Destruye la sesión actual.
        session()->regenerateToken();  // Renueva el token CSRF.

        return redirect()->route('login');
    })->name('logout');
});
