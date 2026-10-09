<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Disciplina del centro (Psicología, Psicopedagogía, Fonoaudiología, etc.).
 */
#[Fillable(['nombre'])]
class Disciplina extends Model
{
    /** Profesionales que tienen esta disciplina. Tabla intermedia: disciplina_user. */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
