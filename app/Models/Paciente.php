<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paciente del centro (niño/a que se atiende).
 */
#[Fillable([
    'nombre',
    'apellido',
    'dni',
    'fecha_nacimiento',
    'fecha_ingreso',
    'obra_social',
    'escuela',
    'grado',
    'diagnostico',
    'activo',
])]
class Paciente extends Model
{
    /**
     * Convierte las columnas al tipo correcto al leerlas de la base.
     *
     * "encrypted" = el dato se guarda cifrado en la base y Laravel lo descifra solo al leerlo,
     * así que en el resto del código se usa como cualquier otro campo. Ver docs/cifrado.md.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dni' => 'encrypted',
            'diagnostico' => 'encrypted',
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'activo' => 'boolean',
        ];
    }

    /** "Apellido, Nombre", como se muestra en las listas. Se usa como $paciente->nombre_completo. */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => "{$this->apellido}, {$this->nombre}");
    }

    /** Años cumplidos hoy. Se usa como $paciente->edad. */
    protected function edad(): Attribute
    {
        return Attribute::get(fn () => (int) $this->fecha_nacimiento->diffInYears(now()));
    }

    /** El DNI se guarda solo con números; esto lo devuelve con puntos (52.118.304). Se usa como $paciente->dni_con_puntos. */
    protected function dniConPuntos(): Attribute
    {
        // Corta el texto de a tres desde la derecha, sin pasarlo a número para no perder ceros a la izquierda.
        return Attribute::get(fn () => strrev(implode('.', str_split(strrev($this->dni), 3))));
    }

    /**
     * Profesionales que atienden a este paciente (su equipo). Tabla intermedia: paciente_user.
     * withPivot trae además hasta cuándo vio cada uno el historial ($profesional->pivot->mensajes_vistos_at).
     */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('mensajes_vistos_at')->withTimestamps();
    }

    /** Mensajes del historial de este paciente. */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    /**
     * ¿Ese usuario puede ver la ficha de este paciente (sus datos)?
     * Dirección ve la de todos; un profesional, solo la de los pacientes que atiende.
     * Ojo: ver la ficha no alcanza para ver el historial de mensajes; eso lo decide esDelEquipo().
     */
    public function puedeVerlo(?User $usuario): bool
    {
        return $usuario !== null && ($usuario->esDireccion() || $this->esDelEquipo($usuario));
    }

    /**
     * ¿Ese usuario es parte del equipo de este paciente?
     * Es la regla que decide quién puede ver y escribir en su historial de mensajes y bajar sus archivos.
     * Vale igual para Dirección: si no está en el equipo, no ve el historial.
     */
    public function esDelEquipo(?User $usuario): bool
    {
        // Sin usuario (nadie inició sesión) nunca es del equipo.
        return $usuario !== null && $this->profesionales()->whereKey($usuario->id)->exists();
    }
}
