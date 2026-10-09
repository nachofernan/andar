<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema (los profesionales del centro).
 *
 * HasRoles (Spatie Permission) le agrega assignRole(), hasRole(), can(), etc.
 */
#[Fillable(['name', 'email', 'password', 'disciplina_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // La contraseña se guarda siempre encriptada.
        ];
    }

    /** Disciplina a la que pertenece el profesional (puede no tener). */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    /** Pacientes que atiende este profesional. Tabla intermedia: paciente_user. */
    public function pacientes(): BelongsToMany
    {
        return $this->belongsToMany(Paciente::class)->withTimestamps();
    }
}
