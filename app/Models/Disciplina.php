<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Disciplina del centro (Psicología, Psicopedagogía, Fonoaudiología, etc.).
 */
#[Fillable(['nombre'])]
class Disciplina extends Model
{
    /** Profesionales que pertenecen a esta disciplina. */
    public function profesionales(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
