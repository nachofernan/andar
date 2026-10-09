<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Mensaje del historial de un paciente: lo que un profesional le escribe al resto del equipo.
 * Puede llevar archivos adjuntos. Una vez enviado no se edita ni se borra.
 *
 * HasMedia + InteractsWithMedia (Spatie Media Library) le agregan el manejo de archivos:
 * cada adjunto es una fila de la tabla "media" que apunta a este mensaje.
 */
#[Fillable(['paciente_id', 'user_id', 'tipo', 'cuerpo'])]
class Mensaje extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * Tipos de mensaje. A la izquierda lo que se guarda en la base, a la derecha lo que se muestra.
     * Para sumar o renombrar un tipo se cambia solo acá.
     */
    public const TIPOS = [
        'mensaje' => 'Mensaje',
        'informe' => 'Informe de evaluación',
        'indicacion' => 'Indicación',
        'nota' => 'Nota de sesión',
    ];

    // Límites de los archivos adjuntos. Para cambiarlos se tocan solo acá.
    public const MAX_ADJUNTOS = 5; // Cuántos archivos por mensaje.

    public const MAX_KB_ADJUNTO = 10240; // Tamaño máximo de cada uno, en KB (10 MB).

    public const EXTENSIONES_ADJUNTO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    /**
     * El texto se guarda cifrado en la base y se descifra solo al leerlo (ver docs/cifrado.md).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cuerpo' => 'encrypted',
        ];
    }

    /** Paciente al que pertenece el mensaje. */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** Profesional que lo escribió. Se aclara 'user_id' porque la relación no se llama "user". */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Guarda un archivo como adjunto de este mensaje, CIFRADO (ver docs/cifrado.md).
     *
     * En el disco queda un archivo ilegible y con un nombre al azar. El nombre original también
     * se guarda cifrado; el tipo y el tamaño quedan a la vista porque no dicen nada del paciente.
     */
    public function adjuntar(UploadedFile $archivo): void
    {
        $this->addMediaFromString(Crypt::encryptString($archivo->get()))
            ->usingFileName(Str::uuid().'.enc')
            ->usingName('adjunto')
            ->withCustomProperties([
                'nombre' => Crypt::encryptString($archivo->getClientOriginalName()),
                'tipo' => $archivo->getMimeType(),
                'tamano' => $archivo->getSize(),
            ])
            ->toMediaCollection('adjuntos');
    }

    /** Nombre original de un adjunto (se guardó cifrado). */
    public static function nombreAdjunto(Media $adjunto): string
    {
        return Crypt::decryptString($adjunto->getCustomProperty('nombre'));
    }

    /** Tamaño original de un adjunto, listo para mostrar: "120 KB" o "2,4 MB". */
    public static function tamanoAdjunto(Media $adjunto): string
    {
        $kb = $adjunto->getCustomProperty('tamano') / 1024;

        return $kb < 1024
            ? max(1, (int) round($kb)).' KB'
            : number_format($kb / 1024, 1, ',', '.').' MB';
    }
}
