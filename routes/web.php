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

    // Administración: todo lo que cuelga de /administracion es solo para Dirección.
    // El control no está acá sino en cada componente (use SoloDireccion; ver docs/roles.md).
    Route::prefix('administracion')->group(function () {
        // Entrar a "Administración" a secas lleva a su primera pantalla.
        Route::get('/', fn () => redirect()->route('pacientes'));

        // Pacientes: el listado y el formulario para crear y editar.
        Route::livewire('/pacientes', Pacientes\Lista::class)->name('pacientes');
        Route::livewire('/pacientes/crear', Pacientes\Formulario::class)->name('pacientes.crear');
        Route::livewire('/pacientes/{paciente}/editar', Pacientes\Formulario::class)->name('pacientes.editar');

        // Profesionales (los usuarios) y disciplinas: comparten el listado; cada uno tiene su formulario.
        Route::livewire('/profesionales', Usuarios\Lista::class)->name('usuarios');
        Route::livewire('/profesionales/crear', Usuarios\Formulario::class)->name('usuarios.crear');
        Route::livewire('/profesionales/{usuario}/editar', Usuarios\Formulario::class)->name('usuarios.editar');
        Route::livewire('/disciplinas/crear', Disciplinas\Formulario::class)->name('disciplinas.crear');
        Route::livewire('/disciplinas/{disciplina}/editar', Disciplinas\Formulario::class)->name('disciplinas.editar');
    });

    // Ficha del paciente: la puede ver cualquiera de su equipo (se llega a ella desde el Inicio).
    Route::livewire('/pacientes/{paciente}', Pacientes\Ficha::class)->name('pacientes.ver');

    // Descarga de un archivo adjunto a un mensaje. El control de quién puede bajarlo está en el controlador.
    Route::get('/adjuntos/{media}', AdjuntoController::class)->name('adjuntos.descargar');

    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();       // Destruye la sesión actual.
        session()->regenerateToken();  // Renueva el token CSRF.

        return redirect()->route('login');
    })->name('logout');
});
