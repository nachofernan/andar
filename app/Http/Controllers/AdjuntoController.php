<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de un archivo adjunto a un mensaje (GET /adjuntos/{media}).
 *
 * Los adjuntos están cifrados en un disco privado, sin link directo: esta es la única forma de bajarlos.
 * Solo puede hacerlo el equipo del paciente al que pertenece el mensaje.
 */
class AdjuntoController extends Controller
{
    public function __invoke(Media $media): StreamedResponse
    {
        // El adjunto pertenece a un mensaje, y el mensaje a un paciente.
        $mensaje = $media->model;
        abort_unless($mensaje instanceof Mensaje, 404);
        abort_unless($mensaje->paciente->esDelEquipo(auth()->user()), 403);

        // Lee el archivo cifrado del disco y lo descifra en memoria.
        $contenido = Crypt::decryptString(Storage::disk($media->disk)->get($media->getPathRelativeToRoot()));

        // Lo entrega como descarga, con su nombre y tipo originales.
        return response()->streamDownload(
            fn () => print ($contenido),
            Mensaje::nombreAdjunto($media),
            ['Content-Type' => $media->getCustomProperty('tipo')],
        );
    }
}
