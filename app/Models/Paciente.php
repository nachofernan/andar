<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
     * Los datos cuyos cambios quedan anotados en el historial del paciente (ver actualizar()).
     * A la izquierda la columna, a la derecha el nombre con el que aparece en la entrada.
     */
    public const DATOS_REGISTRADOS = [
        'nombre' => 'Nombre',
        'apellido' => 'Apellido',
        'dni' => 'DNI',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'fecha_ingreso' => 'Ingreso al espacio',
        'obra_social' => 'Obra social',
        'escuela' => 'Escuela',
        'grado' => 'Grado',
        'diagnostico' => 'Diagnóstico',
        'activo' => 'Estado',
    ];

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

    /** Iniciales del paciente (nombre y apellido), para su círculo en la ficha. Se usa como $paciente->iniciales. */
    protected function iniciales(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(mb_substr($this->nombre, 0, 1).mb_substr($this->apellido, 0, 1)));
    }

    /**
     * Modifica un paciente YA cargado y deja anotado el cambio en su historial (la bitácora).
     * Es el único camino para cambiar sus datos: lo usan el formulario de administración y la ficha (diagnóstico).
     *
     * Compara lo que había con lo que llega, guarda, y si algo cambió crea UNA entrada de tipo "actualizacion"
     * a nombre de $autor, con todos los cambios de este guardado (lo anterior y lo nuevo). Si no cambió nada, no crea nada.
     *
     * @param  array<string, mixed>  $datos  Los datos a guardar (pueden ser todos o solo algunos, ej. solo el diagnóstico).
     * @param  array<int, int|string>|null  $equipo  Ids de los profesionales que quedan como equipo. null = no tocar el equipo.
     */
    public function actualizar(array $datos, ?array $equipo, User $autor): void
    {
        // En una transacción: o se guarda todo (datos, equipo y entrada) o no se guarda nada.
        DB::transaction(function () use ($datos, $equipo, $autor) {
            $antes = $this->datosComoSeLeen();
            $this->fill($datos)->save();
            $ahora = $this->datosComoSeLeen();

            $cambios = [];

            foreach (self::DATOS_REGISTRADOS as $columna => $nombre) {
                if ($antes[$columna] !== $ahora[$columna]) {
                    $cambios[] = ['campo' => $nombre, 'antes' => $antes[$columna], 'ahora' => $ahora[$columna]];
                }
            }

            if ($equipo !== null) {
                // sync deja como equipo exactamente a esos, y devuelve a quiénes agregó y a quiénes sacó.
                $movimientos = $this->profesionales()->sync($equipo);

                foreach (['attached' => 'Se sumó', 'detached' => 'Salió'] as $clave => $verbo) {
                    $nombres = User::whereKey($movimientos[$clave])->orderBy('name')->pluck('name');

                    if ($nombres->isNotEmpty()) {
                        $cambios[] = ['campo' => 'Equipo', 'texto' => $verbo.': '.$nombres->join(', ')];
                    }
                }
            }

            if ($cambios !== []) {
                // El texto del mensaje es la lista de cambios en JSON. Se guarda cifrado, como todo texto de mensaje.
                $this->mensajes()->create([
                    'user_id' => $autor->id,
                    'tipo' => 'actualizacion',
                    'cuerpo' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    /**
     * Los datos registrados, escritos como se leen en pantalla: fechas día/mes/año, el DNI con puntos,
     * el estado como "Activo"/"Inactivo" y null si están vacíos. Sirve para comparar y para anotar los cambios.
     *
     * @return array<string, ?string>
     */
    private function datosComoSeLeen(): array
    {
        $datos = [];

        foreach (array_keys(self::DATOS_REGISTRADOS) as $columna) {
            $valor = $this->$columna;

            $datos[$columna] = match (true) {
                $columna === 'activo' => $valor ? 'Activo' : 'Inactivo',
                $valor === null || $valor === '' => null,
                $columna === 'dni' => $this->dni_con_puntos,
                $valor instanceof \DateTimeInterface => $valor->format('d/m/Y'),
                default => (string) $valor,
            };
        }

        return $datos;
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
