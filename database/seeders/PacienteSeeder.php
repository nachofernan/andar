<?php

namespace Database\Seeders;

use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Carga pacientes de ejemplo con su equipo de profesionales.
 * Son todos INVENTADOS (nombres, DNI y diagnósticos): sirven solo para probar las pantallas.
 * Están pensados para que se vean los distintos casos: con muchos datos o solo los obligatorios,
 * activos e inactivos, con equipo grande, chico o sin equipo.
 * Necesita que EquipoSeeder haya corrido antes.
 *
 * El DNI y el diagnóstico se cifran solos al guardarse (ver docs/cifrado.md).
 */
class PacienteSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pacientes() as $datos) {
            // "equipo" no es una columna del paciente: se saca de los datos y se guarda aparte.
            $mails = $datos['equipo'];
            unset($datos['equipo']);

            $paciente = Paciente::create($datos);

            // Se busca a los profesionales por mail para no depender del número de id.
            $paciente->profesionales()->attach(User::whereIn('email', $mails)->pluck('id'));
        }
    }

    /**
     * La lista de pacientes. En "equipo" van los mails de los profesionales que lo atienden.
     * Lo que no se pone (obra social, escuela, etc.) queda vacío; "activo" queda en sí.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pacientes(): array
    {
        return [
            [
                'nombre' => 'Tomás', 'apellido' => 'González', 'dni' => '52118304',
                'fecha_nacimiento' => '2017-03-14', 'fecha_ingreso' => '2024-04-02',
                'obra_social' => 'OSDE 210', 'escuela' => 'Escuela N.º 12 Manuel Belgrano', 'grado' => '4.º grado',
                'diagnostico' => 'Trastorno específico del lenguaje.',
                'equipo' => ['catalina@andar.test', 'paula@andar.test', 'lucia@andar.test'],
            ],
            [
                'nombre' => 'Valentina', 'apellido' => 'Rodríguez', 'dni' => '54302117',
                'fecha_nacimiento' => '2019-08-22', 'fecha_ingreso' => '2025-03-10',
                'obra_social' => 'Swiss Medical', 'escuela' => 'Colegio San José', 'grado' => '2.º grado',
                'diagnostico' => 'Dificultades en la lectoescritura.',
                'equipo' => ['lucia@andar.test'],
            ],
            [
                'nombre' => 'Benjamín', 'apellido' => 'Fernández', 'dni' => '50877421',
                'fecha_nacimiento' => '2015-11-05', 'fecha_ingreso' => '2022-08-15',
                'obra_social' => 'IOMA', 'escuela' => 'Escuela N.º 5 Domingo F. Sarmiento', 'grado' => '6.º grado',
                // Diagnóstico largo y en varios renglones, para ver cómo queda en la ficha.
                'diagnostico' => "Trastorno del espectro autista (nivel 1).\n\n"
                    ."Buen desempeño académico, con dificultades en la interacción con pares y en los cambios de rutina. "
                    ."Se trabaja en habilidades sociales y en anticipación de actividades.\n\n"
                    .'Tiene certificado de discapacidad vigente y acompañante en la escuela.',
                'equipo' => ['catalina@andar.test', 'sofia@andar.test', 'julieta@andar.test'],
            ],
            [
                'nombre' => 'Emma', 'apellido' => 'López', 'dni' => '57440982',
                'fecha_nacimiento' => '2022-01-30', 'fecha_ingreso' => '2026-02-18',
                'obra_social' => 'Galeno', 'escuela' => 'Jardín Rayito de Sol', 'grado' => 'Sala de 4',
                'diagnostico' => 'Retraso en la adquisición del lenguaje.',
                'equipo' => ['paula@andar.test', 'diego@andar.test'],
            ],
            [
                'nombre' => 'Mateo', 'apellido' => 'Martínez', 'dni' => '53665190',
                'fecha_nacimiento' => '2018-06-17', 'fecha_ingreso' => '2025-05-06',
                'obra_social' => 'OSECAC', 'escuela' => 'Escuela N.º 23', 'grado' => '3.º grado',
                'diagnostico' => 'Trastorno por déficit de atención con hiperactividad.',
                'equipo' => ['martin@andar.test', 'lucia@andar.test'],
            ],
            [
                // Solo los cuatro datos obligatorios y una profesional: recién ingresada.
                'nombre' => 'Olivia', 'apellido' => 'Pérez', 'dni' => '56120348',
                'fecha_nacimiento' => '2020-09-09',
                'equipo' => ['catalina@andar.test'],
            ],
            [
                'nombre' => 'Santino', 'apellido' => 'Gómez', 'dni' => '49903277',
                'fecha_nacimiento' => '2014-02-11', 'fecha_ingreso' => '2021-03-22',
                'obra_social' => 'PAMI', 'escuela' => 'Instituto Nuestra Señora de Luján', 'grado' => '1.º año',
                'diagnostico' => 'Dislexia.',
                'equipo' => ['lucia@andar.test', 'martin@andar.test'],
            ],
            [
                'nombre' => 'Isabella', 'apellido' => 'Díaz', 'dni' => '55781604',
                'fecha_nacimiento' => '2020-04-03', 'fecha_ingreso' => '2025-09-01',
                'obra_social' => 'Medifé', 'escuela' => 'Colegio del Sol', 'grado' => '1.º grado',
                'diagnostico' => 'Dificultades en la motricidad fina.',
                'equipo' => ['sofia@andar.test', 'diego@andar.test'],
            ],
            [
                // Sin equipo asignado todavía.
                'nombre' => 'Lautaro', 'apellido' => 'Sánchez', 'dni' => '58006731',
                'fecha_nacimiento' => '2023-05-25', 'fecha_ingreso' => '2026-09-14',
                'obra_social' => 'OSDE 310',
                'equipo' => [],
            ],
            [
                'nombre' => 'Mía', 'apellido' => 'Romero', 'dni' => '51244865',
                'fecha_nacimiento' => '2016-07-19', 'fecha_ingreso' => '2023-04-11',
                'obra_social' => 'Sancor Salud', 'escuela' => 'Escuela N.º 8 Juana Azurduy', 'grado' => '5.º grado',
                'diagnostico' => 'Ansiedad. Dificultades para separarse de los padres.',
                'equipo' => ['catalina@andar.test'],
            ],
            [
                // DNI de siete dígitos, para ver cómo se muestra con puntos (5.123.456).
                'nombre' => 'Joaquín', 'apellido' => 'Álvarez', 'dni' => '5123456',
                'fecha_nacimiento' => '2012-12-01', 'fecha_ingreso' => '2020-08-03',
                'obra_social' => 'IOMA', 'escuela' => 'Escuela Secundaria N.º 4', 'grado' => '2.º año',
                'diagnostico' => 'Trastorno del espectro autista (nivel 2).',
                'equipo' => ['martin@andar.test', 'sofia@andar.test', 'julieta@andar.test'],
            ],
            [
                'nombre' => 'Catalina', 'apellido' => 'Torres', 'dni' => '56934012',
                'fecha_nacimiento' => '2021-10-12', 'fecha_ingreso' => '2026-03-03',
                'escuela' => 'Jardín N.º 903', 'grado' => 'Sala de 5',
                'diagnostico' => 'Tartamudez.',
                'equipo' => ['paula@andar.test'],
            ],
            [
                // Dado de baja: terminó el tratamiento.
                'nombre' => 'Thiago', 'apellido' => 'Ruiz', 'dni' => '50310598',
                'fecha_nacimiento' => '2015-01-27', 'fecha_ingreso' => '2021-05-17',
                'obra_social' => 'OSDE 210', 'escuela' => 'Colegio San José', 'grado' => '6.º grado',
                'diagnostico' => 'Dificultades en el cálculo. Alta en diciembre de 2025.',
                'activo' => false,
                'equipo' => ['lucia@andar.test'],
            ],
            [
                // Dada de baja: se mudó.
                'nombre' => 'Delfina', 'apellido' => 'Benítez', 'dni' => '53092746',
                'fecha_nacimiento' => '2018-02-08', 'fecha_ingreso' => '2023-09-05',
                'obra_social' => 'Swiss Medical',
                'diagnostico' => 'Retraso madurativo.',
                'activo' => false,
                'equipo' => ['diego@andar.test', 'paula@andar.test'],
            ],
            [
                'nombre' => 'Bautista', 'apellido' => 'Castro', 'dni' => '54718263',
                'fecha_nacimiento' => '2019-12-20', 'fecha_ingreso' => '2026-06-08',
                'obra_social' => 'Galeno', 'escuela' => 'Escuela N.º 12 Manuel Belgrano', 'grado' => '1.º grado',
                'equipo' => ['martin@andar.test', 'sofia@andar.test'],
            ],
        ];
    }
}
