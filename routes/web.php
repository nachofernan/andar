<?php

use App\Livewire\Disciplinas;
use App\Livewire\Login;
use App\Livewire\Pacientes;
use App\Livewire\Usuarios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Login: solo para quien NO inició sesión (si ya la tiene, lo manda al inicio).
Route::livewire('/login', Login::class)->name('login')->middleware('guest');

// Todo lo de acá adentro exige sesión iniciada (si no, redirige a /login).
Route::middleware('auth')->group(function () {
    Route::view('/', 'inicio')->name('inicio');

    // Por ahora entra cualquier usuario con sesión; se restringe cuando haya roles.
    Route::livewire('/disciplinas', Disciplinas::class)->name('disciplinas');
    Route::livewire('/usuarios', Usuarios::class)->name('usuarios');

    // Pacientes. "crear" va antes que "{paciente}" para que no se confunda con un id.
    Route::livewire('/pacientes', Pacientes\Lista::class)->name('pacientes');
    Route::livewire('/pacientes/crear', Pacientes\Formulario::class)->name('pacientes.crear');
    Route::livewire('/pacientes/{paciente}', Pacientes\Ficha::class)->name('pacientes.ver');
    Route::livewire('/pacientes/{paciente}/editar', Pacientes\Formulario::class)->name('pacientes.editar');

    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();       // Destruye la sesión actual.
        session()->regenerateToken();  // Renueva el token CSRF.

        return redirect()->route('login');
    })->name('logout');
});
