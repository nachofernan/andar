<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Disciplina del centro (Psicología, Psicopedagogía, Fonoaudiología, etc.).
 * Cada una tiene un color (en hexa) con el que se pintan sus etiquetas en las pantallas.
 */
#[Fillable(['nombre', 'color'])]
class Disciplina extends Model
{
    /** Color con el que nace toda disciplina: el violeta de la app. Es el mismo valor por defecto de la columna. */
    public const COLOR_INICIAL = '#7c5cf0';

    /** Valores con los que arranca una disciplina nueva antes de guardarse (la base pone el mismo color por defecto). */
    protected $attributes = [
        'color' => self::COLOR_INICIAL,
    ];

    /** Profesionales que tienen esta disciplina. Tabla intermedia: disciplina_user. */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
