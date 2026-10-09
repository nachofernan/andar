<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    /** Profesionales que atienden a este paciente (su equipo). Tabla intermedia: paciente_user. */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
