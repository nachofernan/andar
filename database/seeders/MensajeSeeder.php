<?php

namespace Database\Seeders;

use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Carga mensajes de ejemplo en el historial de algunos pacientes.
 * Son INVENTADOS, igual que los pacientes. Cada mensaje lo escribe alguien del equipo de ese paciente.
 * Necesita que PacienteSeeder haya corrido antes.
 *
 * El texto se cifra solo al guardarse (ver docs/cifrado.md).
 */
class MensajeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->mensajes() as [$dniPaciente, $emailAutor, $tipo, $fecha, $cuerpo]) {
            // El DNI está cifrado, así que no se puede buscar con where: se traen todos y se compara acá.
            $paciente = Paciente::all()->firstWhere('dni', $dniPaciente);

            $mensaje = new Mensaje([
                'paciente_id' => $paciente->id,
                'user_id' => User::where('email', $emailAutor)->value('id'),
                'tipo' => $tipo,
                'cuerpo' => $cuerpo,
            ]);

            // Fecha fija en vez de "ahora", para que el historial de ejemplo sea siempre el mismo.
            $mensaje->created_at = $fecha;
            $mensaje->updated_at = $fecha;
            $mensaje->save();
        }

        // Catalina ya abrió el historial de Tomás González el 5/10 al mediodía: le quedan sin leer solo los
        // mensajes posteriores. Todos los demás nunca abrieron ningún historial, así que tienen todo sin leer.
        // Sirve para ver los avisos de mensajes sin leer apenas se entra (ver docs/mensajes.md).
        Paciente::all()->firstWhere('dni', '52118304')->profesionales()->updateExistingPivot(
            User::where('email', 'catalina@andar.test')->value('id'),
            ['mensajes_vistos_at' => '2026-10-05 12:00:00'],
        );
    }

    /**
     * La lista de mensajes: DNI del paciente, mail de quien escribe, tipo (ver Mensaje::TIPOS), fecha y texto.
     *
     * @return array<int, array{string, string, string, string, string}>
     */
    private function mensajes(): array
    {
        return [
            // Tomás González
            ['52118304', 'paula@andar.test', 'informe', '2026-10-05 10:42',
                "Terminé la evaluación de conciencia fonológica. Se observan dificultades en segmentación silábica y en rimas.\nComprensión oral dentro de lo esperado."],
            ['52118304', 'lucia@andar.test', 'mensaje', '2026-10-05 11:15',
                'Coincide con lo que veo en sesión: aparece frustración y evitación cuando hay consignas de lectura en voz alta.'],
            ['52118304', 'catalina@andar.test', 'mensaje', '2026-10-08 09:30',
                "Gracias a ambas. Desde psicología voy a trabajar la tolerancia a la frustración en las próximas sesiones.\n\n¿Podemos coordinar una reunión con la escuela antes de fin de mes?"],
            ['52118304', 'paula@andar.test', 'indicacion', '2026-10-08 09:48',
                'Para trabajar en casa: diez minutos por día de juegos de rimas, sin corregir los errores en el momento.'],

            // Benjamín Fernández
            ['50877421', 'sofia@andar.test', 'nota', '2026-09-29 16:05',
                'Sesión tranquila. Toleró bien el cambio de consultorio cuando se lo anticipé con el calendario visual.'],
            ['50877421', 'julieta@andar.test', 'mensaje', '2026-10-01 13:20',
                'En la escuela hubo un episodio en el recreo por un cambio de horario que no se le avisó. Hablé con la docente.'],
            ['50877421', 'catalina@andar.test', 'indicacion', '2026-10-02 10:00',
                'Pedir a la escuela que avise los cambios de horario el día anterior, y sumarlos al calendario visual.'],

            // Mía Romero
            ['51244865', 'catalina@andar.test', 'nota', '2026-10-06 18:10',
                'Entró sola al consultorio por primera vez. La madre esperó en la sala.'],

            // Mateo Martínez
            ['53665190', 'martin@andar.test', 'informe', '2026-09-22 12:00',
                'Informe de avance del trimestre: mejoró el tiempo de atención sostenida en tareas cortas. Persiste la impulsividad en actividades grupales.'],
            ['53665190', 'lucia@andar.test', 'mensaje', '2026-09-23 08:45',
                'Lo veo igual. En lectoescritura avanza bien cuando las consignas van de a una.'],
        ];
    }
}
