<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema (los profesionales del centro).
 *
 * HasRoles (Spatie Permission) le agrega syncRoles(), hasRole(), etc. Los roles están en la constante ROLES.
 */
#[Fillable(['name', 'email', 'telefono', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Roles de la app. A la izquierda lo que se guarda en la base, a la derecha lo que se muestra.
     * Cada usuario tiene exactamente uno. Qué puede hacer cada rol: docs/roles.md.
     */
    public const ROLES = [
        'direccion' => 'Dirección',
        'profesional' => 'Profesional',
    ];

    /** ¿Es de Dirección? Dirección carga y edita pacientes, equipos, usuarios y disciplinas. */
    public function esDireccion(): bool
    {
        return $this->hasRole('direccion');
    }

    /** El rol del usuario tal como se guarda ("direccion" o "profesional"). Se usa como $usuario->rol. */
    protected function rol(): Attribute
    {
        return Attribute::get(fn () => $this->roles->first()?->name);
    }

    /** Iniciales del usuario (de sus dos primeras palabras), para su círculo en el historial. Se usa como $usuario->iniciales. */
    protected function iniciales(): Attribute
    {
        return Attribute::get(fn () => collect(preg_split('/\s+/', trim($this->name)))
            ->take(2)
            ->map(fn (string $palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
            ->implode(''));
    }

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

    /** Disciplinas del profesional (puede tener varias o ninguna), por orden alfabético. Tabla intermedia: disciplina_user. */
    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class)->withTimestamps()->orderBy('nombre');
    }

    /** Pacientes que atiende este profesional. Tabla intermedia: paciente_user. */
    public function pacientes(): BelongsToMany
    {
        return $this->belongsToMany(Paciente::class)->withPivot('mensajes_vistos_at')->withTimestamps();
    }

    /**
     * Cuántos mensajes sin leer tiene este profesional en cada paciente.
     * Devuelve una lista "id del paciente => cantidad", solo con los pacientes que tienen alguno.
     * De acá salen todos los avisos: el número del menú, los de la lista de pacientes y el inicio.
     *
     * Un mensaje está sin leer si es de un paciente de su equipo, lo escribió otra persona
     * y es posterior a la última vez que abrió ese historial (o nunca lo abrió).
     *
     * @return Collection<int, int>
     */
    public function mensajesSinLeer(): Collection
    {
        return Mensaje::query()
            // Une cada mensaje con la fila de paciente_user de este profesional: así quedan solo los de su equipo.
            ->join('paciente_user', function ($union) {
                $union->on('paciente_user.paciente_id', '=', 'mensajes.paciente_id')
                    ->where('paciente_user.user_id', $this->id);
            })
            ->where('mensajes.user_id', '!=', $this->id)
            ->where(function ($consulta) {
                $consulta->whereNull('paciente_user.mensajes_vistos_at')
                    ->orWhereColumn('mensajes.created_at', '>', 'paciente_user.mensajes_vistos_at');
            })
            ->groupBy('mensajes.paciente_id')
            ->selectRaw('mensajes.paciente_id, count(*) as cantidad')
            ->pluck('cantidad', 'paciente_id');
    }
}
