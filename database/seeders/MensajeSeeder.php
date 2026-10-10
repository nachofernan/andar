<?php

namespace Database\Seeders;

use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Carga mensajes de ejemplo en el historial de los pacientes.
 * Son INVENTADOS, igual que los pacientes. Cada mensaje lo escribe alguien del equipo de ese paciente.
 * Hay dos tandas: unos pocos escritos a mano (mensajes()), que arman conversaciones con sentido,
 * y el resto armado solo rotando frases (generarMensajes()), para que haya volumen.
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
        // mensajes posteriores. En los otros tres pacientes de arriba nadie abrió el historial todavía.
        // Sirve para ver los avisos de mensajes sin leer apenas se entra (ver docs/mensajes.md).
        Paciente::all()->firstWhere('dni', '52118304')->profesionales()->updateExistingPivot(
            User::where('email', 'catalina@andar.test')->value('id'),
            ['mensajes_vistos_at' => '2026-10-05 12:00:00'],
        );

        $this->generarMensajes();
    }

    /**
     * Le arma un historial al resto de los pacientes, para que haya volumen sin escribir cada mensaje a mano.
     * No usa el azar: todo sale del número de orden del paciente y de ir rotando las frases de frases(),
     * así que cada vez que se corre queda exactamente lo mismo.
     *
     * Saltea a los pacientes sin equipo (no hay quién escriba) y a los que ya tienen mensajes escritos a mano.
     */
    private function generarMensajes(): void
    {
        $frases = $this->frases();

        // El orden en que se van turnando los tipos: más mensajes y notas que informes e indicaciones.
        $tipos = ['nota', 'mensaje', 'informe', 'mensaje', 'nota', 'indicacion', 'mensaje'];

        $numero = 0; // Cuántos mensajes se generaron hasta ahora: de acá sale qué tipo y qué frase toca.

        foreach (Paciente::with('profesionales')->orderBy('id')->get() as $orden => $paciente) {
            $equipo = $paciente->profesionales->sortBy('id')->values();

            if ($equipo->isEmpty() || $paciente->mensajes()->exists()) {
                continue;
            }

            // Entre 0 y 6 mensajes según el orden del paciente: algunos quedan sin historial a propósito.
            $cantidad = $orden % 7;

            // El último mensaje de cada paciente cae entre el 30/9 y el 9/10; los anteriores, cada tres días hacia atrás.
            $ultimo = Carbon::parse('2026-10-09 09:00')->subDays($orden % 10);

            for ($i = 0; $i < $cantidad; $i++) {
                $tipo = $tipos[$numero % count($tipos)];
                $fecha = $ultimo->copy()->subDays(($cantidad - 1 - $i) * 3)->addMinutes($numero * 37 % 480);

                $mensaje = new Mensaje([
                    'paciente_id' => $paciente->id,
                    // El autor va rotando entre el equipo: así siempre escribe alguien que atiende al paciente.
                    'user_id' => $equipo[$numero % $equipo->count()]->id,
                    'tipo' => $tipo,
                    'cuerpo' => $frases[$tipo][$numero % count($frases[$tipo])],
                ]);
                $mensaje->created_at = $fecha;
                $mensaje->updated_at = $fecha;
                $mensaje->save();

                $numero++;
            }

            // En uno de cada dos pacientes, todo el equipo ya vio el historial hasta el 6/10 al mediodía:
            // les queda sin leer solo lo posterior. En los demás nadie lo abrió, así que tienen todo sin leer.
            if ($orden % 2 === 0) {
                foreach ($equipo as $profesional) {
                    $paciente->profesionales()->updateExistingPivot(
                        $profesional->id,
                        ['mensajes_vistos_at' => '2026-10-06 12:00:00'],
                    );
                }
            }
        }
    }

    /**
     * Frases para los mensajes generados, agrupadas por tipo (ver Mensaje::TIPOS).
     * Son genéricas a propósito, para que sirvan para cualquier paciente.
     *
     * @return array<string, array<int, string>>
     */
    private function frases(): array
    {
        return [
            'mensaje' => [
                'Hoy vino con muy buena predisposición. ¿Alguien más notó el cambio esta semana?',
                'Hablé con la mamá: en casa están más tranquilos desde que ordenaron los horarios de sueño.',
                'La docente pidió una reunión con el equipo. ¿Les sirve el jueves a la tarde?',
                'Aviso que la semana que viene no viene: la familia viaja. Retomamos el lunes siguiente.',
                'Coincido con lo que comentaron. Yo también veo que le cuesta arrancar, pero después engancha.',
                'Llegó la autorización de la obra social por seis meses más. Ya está cargada.',
                'El papá preguntó si puede pasar a sala al final de la sesión. Por mi parte no hay problema.',
                'Me escribió la maestra integradora: esta semana trabajó mejor en grupo.',
                '¿Alguien tiene el último informe de la escuela? No lo encuentro entre los archivos.',
                'Cambio de horario: desde el mes que viene pasa a los martes a las 16.',
                'Vino con resfrío y con sueño, así que hicimos una sesión más corta y tranquila.',
                'Gracias por el dato. Lo tengo en cuenta para la próxima sesión.',
            ],
            'nota' => [
                'Sesión tranquila. Trabajamos con juegos de mesa y esperó su turno sin ayuda.',
                'Costó el arranque: entró con enojo porque quería quedarse en la sala de espera. A los diez minutos se acomodó.',
                'Hoy propuso el juego por iniciativa propia. Buen contacto visual durante toda la sesión.',
                'Se frustró con el rompecabezas y quiso dejarlo. Lo retomamos al final y lo terminó.',
                'Trabajamos con cuentos. Pudo contar lo que pasaba con sus palabras, con poca ayuda.',
                'Llegó tarde, sesión de media hora. Hicimos solo la parte de juego en el piso.',
                'Hoy habló un montón. Contó lo que hizo el fin de semana sin que se lo pregunte.',
                'Probamos el circuito nuevo: bien el equilibrio, le cuesta todavía saltar con los dos pies.',
                'Toleró bien el cambio de actividad cuando se lo anticipé con el reloj de arena.',
                'Sesión compartida con la mamá. Le mostré cómo seguir en casa el juego de turnos.',
            ],
            'informe' => [
                'Informe de avance del trimestre: se sostienen los logros y mejora la tolerancia a la espera. Sugiero mantener la frecuencia.',
                "Terminé la evaluación inicial.\nComprensión dentro de lo esperado para la edad; las dificultades aparecen en la expresión y en sostener la atención.",
                'Informe para la escuela enviado. Resumen: avanza a su ritmo, necesita consignas cortas y de a una.',
                'Reevaluación a los seis meses: avances claros en la motricidad fina. Falta trabajar la prensión del lápiz.',
                'Informe para la obra social presentado, con el pedido de renovación de las sesiones.',
                "Resumen de la entrevista con los padres.\nDescriben berrinches al salir de casa y buen descanso. Acordamos volver a vernos en un mes.",
            ],
            'indicacion' => [
                'Para casa: diez minutos por día de lectura compartida, sin corregir en el momento.',
                'Anticipar los cambios de rutina con el calendario visual, tanto en casa como en la escuela.',
                'Evitar pantallas una hora antes de dormir. Lo hablé con la familia y están de acuerdo.',
                'En la escuela: que se siente cerca del pizarrón y darle las consignas de a una.',
                'Para casa: juegos de soplo y de imitación de sonidos, un ratito todos los días.',
                'Que se vista sin ayuda aunque tarde más: es parte de lo que estamos trabajando.',
            ],
        ];
    }

    /**
     * La lista de mensajes escritos a mano: DNI del paciente, mail de quien escribe, tipo (ver Mensaje::TIPOS), fecha y texto.
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
