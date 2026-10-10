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
 * Son 60: los primeros 15 tienen los casos especiales y el resto está para darle volumen a las tablas.
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

            // De acá para abajo: más pacientes, para que las tablas tengan volumen (buscar, filtrar, ordenar).
            [
                'nombre' => 'Francisco', 'apellido' => 'Herrera', 'dni' => '51530218',
                'fecha_nacimiento' => '2016-05-02', 'fecha_ingreso' => '2023-03-13',
                'obra_social' => 'OSDE 210', 'escuela' => 'Escuela N.º 23', 'grado' => '5.º grado',
                'diagnostico' => 'Trastorno del espectro autista (nivel 1).',
                'equipo' => ['catalina@andar.test', 'gabriela@andar.test', 'belen@andar.test'],
            ],
            [
                'nombre' => 'Martina', 'apellido' => 'Suárez', 'dni' => '53410776',
                'fecha_nacimiento' => '2018-09-14', 'fecha_ingreso' => '2025-04-07',
                'obra_social' => 'Swiss Medical', 'escuela' => 'Colegio del Sol', 'grado' => '2.º grado',
                'diagnostico' => 'Dificultades en la lectoescritura.',
                'equipo' => ['camila@andar.test'],
            ],
            [
                'nombre' => 'Felipe', 'apellido' => 'Aguirre', 'dni' => '55530489',
                'fecha_nacimiento' => '2020-01-21', 'fecha_ingreso' => '2025-08-11',
                'obra_social' => 'IOMA', 'escuela' => 'Colegio San José', 'grado' => '1.º grado',
                'diagnostico' => 'Trastorno del desarrollo del lenguaje.',
                'equipo' => ['micaela@andar.test', 'florencia@andar.test'],
            ],
            [
                'nombre' => 'Sofía', 'apellido' => 'Molina', 'dni' => '56702153',
                'fecha_nacimiento' => '2021-06-30', 'fecha_ingreso' => '2026-03-16',
                'obra_social' => 'Galeno', 'escuela' => 'Jardín Rayito de Sol', 'grado' => 'Sala de 5',
                'diagnostico' => 'Retraso en la adquisición del lenguaje.',
                'equipo' => ['veronica@andar.test', 'carolina@andar.test'],
            ],
            [
                'nombre' => 'Lucas', 'apellido' => 'Ortiz', 'dni' => '50120934',
                'fecha_nacimiento' => '2014-08-08', 'fecha_ingreso' => '2022-02-21',
                'obra_social' => 'OSECAC', 'escuela' => 'Escuela N.º 5 Domingo F. Sarmiento', 'grado' => '6.º grado',
                'diagnostico' => 'Trastorno por déficit de atención sin hiperactividad.',
                'equipo' => ['catalina@andar.test', 'nicolas@andar.test'],
            ],
            [
                'nombre' => 'Juana', 'apellido' => 'Silva', 'dni' => '54098312',
                'fecha_nacimiento' => '2019-03-27', 'fecha_ingreso' => '2025-06-02',
                'obra_social' => 'Medifé', 'escuela' => 'Escuela N.º 8 Juana Azurduy', 'grado' => '2.º grado',
                'diagnostico' => 'Mutismo selectivo.',
                'equipo' => ['mariana@andar.test', 'paula@andar.test'],
            ],
            [
                'nombre' => 'Ignacio', 'apellido' => 'Rojas', 'dni' => '52690447',
                'fecha_nacimiento' => '2017-11-11', 'fecha_ingreso' => '2024-09-09',
                'obra_social' => 'Sancor Salud', 'escuela' => 'Escuela N.º 12 Manuel Belgrano', 'grado' => '3.º grado',
                'diagnostico' => 'Dispraxia. Dificultades en la coordinación motora.',
                'equipo' => ['federico@andar.test', 'ezequiel@andar.test'],
            ],
            [
                'nombre' => 'Abril', 'apellido' => 'Medina', 'dni' => '57501268',
                'fecha_nacimiento' => '2022-04-18', 'fecha_ingreso' => '2026-05-04',
                'obra_social' => 'OSDE 310', 'escuela' => 'Jardín N.º 903', 'grado' => 'Sala de 4',
                'diagnostico' => 'Retraso madurativo.',
                'equipo' => ['catalina@andar.test', 'diego@andar.test', 'micaela@andar.test'],
            ],
            [
                // Equipo grande: cuatro profesionales.
                'nombre' => 'Simón', 'apellido' => 'Castillo', 'dni' => '50640871',
                'fecha_nacimiento' => '2015-06-23', 'fecha_ingreso' => '2021-10-04',
                'obra_social' => 'IOMA', 'escuela' => 'Escuela N.º 5 Domingo F. Sarmiento', 'grado' => '6.º grado',
                'diagnostico' => 'Trastorno del espectro autista (nivel 2).',
                'equipo' => ['agustin@andar.test', 'sofia@andar.test', 'tamara@andar.test', 'veronica@andar.test'],
            ],
            [
                'nombre' => 'Renata', 'apellido' => 'Vega', 'dni' => '53720595',
                'fecha_nacimiento' => '2018-12-03', 'fecha_ingreso' => '2024-11-18',
                'obra_social' => 'Swiss Medical', 'escuela' => 'Colegio San José', 'grado' => '2.º grado',
                'diagnostico' => 'Discalculia.',
                'equipo' => ['lucia@andar.test'],
            ],
            [
                'nombre' => 'Dante', 'apellido' => 'Cabrera', 'dni' => '49150362',
                'fecha_nacimiento' => '2013-04-15', 'fecha_ingreso' => '2020-03-09',
                'obra_social' => 'PAMI', 'escuela' => 'Escuela Secundaria N.º 4', 'grado' => '2.º año',
                'diagnostico' => 'Ansiedad social.',
                'equipo' => ['martin@andar.test'],
            ],
            [
                'nombre' => 'Alma', 'apellido' => 'Ríos', 'dni' => '55910724',
                'fecha_nacimiento' => '2020-07-07', 'fecha_ingreso' => '2026-04-13',
                'obra_social' => 'Galeno', 'escuela' => 'Jardín Rayito de Sol', 'grado' => 'Sala de 5',
                'diagnostico' => 'Tartamudez.',
                'equipo' => ['paula@andar.test', 'catalina@andar.test'],
            ],
            [
                'nombre' => 'Bruno', 'apellido' => 'Navarro', 'dni' => '51870143',
                'fecha_nacimiento' => '2016-10-29', 'fecha_ingreso' => '2023-08-07',
                'obra_social' => 'OSDE 210', 'escuela' => 'Escuela N.º 23', 'grado' => '4.º grado',
                'diagnostico' => 'Trastorno por déficit de atención con hiperactividad.',
                'equipo' => ['florencia@andar.test', 'camila@andar.test', 'gabriela@andar.test'],
            ],
            [
                'nombre' => 'Julia', 'apellido' => 'Peralta', 'dni' => '54260981',
                'fecha_nacimiento' => '2019-05-16', 'fecha_ingreso' => '2025-10-06',
                'obra_social' => 'OSECAC', 'escuela' => 'Escuela N.º 8 Juana Azurduy', 'grado' => '2.º grado',
                'diagnostico' => 'Dificultades en la motricidad fina.',
                'equipo' => ['sofia@andar.test'],
            ],
            [
                'nombre' => 'Ramiro', 'apellido' => 'Luna', 'dni' => '48530617',
                'fecha_nacimiento' => '2012-09-02', 'fecha_ingreso' => '2019-08-12',
                'obra_social' => 'IOMA', 'escuela' => 'Escuela Secundaria N.º 4', 'grado' => '2.º año',
                'diagnostico' => 'Trastorno del espectro autista (nivel 1).',
                'equipo' => ['catalina@andar.test', 'tamara@andar.test'],
            ],
            [
                // Sin diagnóstico todavía: está en evaluación.
                'nombre' => 'Elena', 'apellido' => 'Campos', 'dni' => '56480239',
                'fecha_nacimiento' => '2021-02-25', 'fecha_ingreso' => '2026-07-20',
                'obra_social' => 'Medifé', 'escuela' => 'Jardín Rayito de Sol', 'grado' => 'Sala de 5',
                'equipo' => ['mariana@andar.test', 'micaela@andar.test'],
            ],
            [
                'nombre' => 'Gael', 'apellido' => 'Ojeda', 'dni' => '52340856',
                'fecha_nacimiento' => '2017-07-04', 'fecha_ingreso' => '2024-03-04',
                'obra_social' => 'Sancor Salud', 'escuela' => 'Colegio San José', 'grado' => '3.º grado',
                'diagnostico' => 'Dislexia.',
                'equipo' => ['nicolas@andar.test', 'lucia@andar.test'],
            ],
            [
                'nombre' => 'Victoria', 'apellido' => 'Quiroga', 'dni' => '50370492',
                'fecha_nacimiento' => '2014-12-19', 'fecha_ingreso' => '2022-06-13',
                'obra_social' => 'OSDE 310', 'escuela' => 'Instituto Nuestra Señora de Luján', 'grado' => '6.º grado',
                'diagnostico' => 'Trastorno de ansiedad generalizada.',
                'equipo' => ['catalina@andar.test', 'florencia@andar.test'],
            ],
            [
                'nombre' => 'Lorenzo', 'apellido' => 'Paz', 'dni' => '57760318',
                'fecha_nacimiento' => '2022-08-13', 'fecha_ingreso' => '2026-08-03',
                'obra_social' => 'Swiss Medical', 'escuela' => 'Jardín N.º 903', 'grado' => 'Sala de 3',
                'diagnostico' => 'Retraso en la adquisición del lenguaje.',
                'equipo' => ['veronica@andar.test'],
            ],
            [
                'nombre' => 'Amparo', 'apellido' => 'Vera', 'dni' => '53180645',
                'fecha_nacimiento' => '2018-03-09', 'fecha_ingreso' => '2024-05-20',
                'obra_social' => 'IOMA', 'escuela' => 'Escuela N.º 12 Manuel Belgrano', 'grado' => '3.º grado',
                'diagnostico' => 'Hipotonía. Dificultades en el equilibrio y la postura.',
                'equipo' => ['carolina@andar.test', 'gabriela@andar.test'],
            ],
            [
                'nombre' => 'Nicolás', 'apellido' => 'Acosta', 'dni' => '50790236',
                'fecha_nacimiento' => '2015-09-17', 'fecha_ingreso' => '2022-04-04',
                'obra_social' => 'OSECAC', 'escuela' => 'Escuela N.º 23', 'grado' => '5.º grado',
                'diagnostico' => 'Trastorno del espectro autista (nivel 3).',
                'equipo' => ['catalina@andar.test', 'sofia@andar.test', 'micaela@andar.test', 'belen@andar.test'],
            ],
            [
                'nombre' => 'Paulina', 'apellido' => 'Ferreyra', 'dni' => '56070891',
                'fecha_nacimiento' => '2020-11-05', 'fecha_ingreso' => '2026-02-09',
                'obra_social' => 'Galeno', 'escuela' => 'Jardín Rayito de Sol', 'grado' => 'Sala de 5',
                'diagnostico' => 'Dificultades en la integración sensorial.',
                'equipo' => ['gabriela@andar.test', 'ezequiel@andar.test'],
            ],
            [
                'nombre' => 'Valentín', 'apellido' => 'Maldonado', 'dni' => '49480715',
                'fecha_nacimiento' => '2013-10-22', 'fecha_ingreso' => '2021-08-16',
                'obra_social' => 'OSDE 210', 'escuela' => 'Escuela Secundaria N.º 4', 'grado' => '1.º año',
                'diagnostico' => 'Dificultades en la comprensión lectora.',
                'equipo' => ['camila@andar.test', 'mariana@andar.test'],
            ],
            [
                'nombre' => 'Lola', 'apellido' => 'Giménez', 'dni' => '54630427',
                'fecha_nacimiento' => '2019-10-01', 'fecha_ingreso' => '2025-03-17',
                'obra_social' => 'Medifé', 'escuela' => 'Colegio del Sol', 'grado' => '1.º grado',
                'diagnostico' => 'Dislalia.',
                'equipo' => ['paula@andar.test'],
            ],
            [
                'nombre' => 'Tobías', 'apellido' => 'Ibáñez', 'dni' => '51290568',
                'fecha_nacimiento' => '2016-02-14', 'fecha_ingreso' => '2023-05-08',
                'obra_social' => 'Sancor Salud', 'escuela' => 'Escuela N.º 8 Juana Azurduy', 'grado' => '5.º grado',
                'diagnostico' => 'Trastorno por déficit de atención con hiperactividad.',
                'equipo' => ['catalina@andar.test', 'lucia@andar.test', 'federico@andar.test'],
            ],
            [
                // Sin obra social.
                'nombre' => 'Emilia', 'apellido' => 'Sosa', 'dni' => '57040193',
                'fecha_nacimiento' => '2021-12-08', 'fecha_ingreso' => '2026-06-22',
                'escuela' => 'Jardín N.º 903', 'grado' => 'Sala de 4',
                'diagnostico' => 'Retraso madurativo.',
                'equipo' => ['diego@andar.test', 'veronica@andar.test'],
            ],
            [
                'nombre' => 'Máximo', 'apellido' => 'Roldán', 'dni' => '52010784',
                'fecha_nacimiento' => '2017-01-26', 'fecha_ingreso' => '2023-11-06',
                'obra_social' => 'IOMA', 'escuela' => 'Escuela N.º 5 Domingo F. Sarmiento', 'grado' => '4.º grado',
                'diagnostico' => 'Trastorno oposicionista desafiante.',
                'equipo' => ['martin@andar.test', 'agustin@andar.test'],
            ],
            [
                'nombre' => 'Pilar', 'apellido' => 'Arias', 'dni' => '49960325',
                'fecha_nacimiento' => '2014-05-31', 'fecha_ingreso' => '2021-04-19',
                'obra_social' => 'Swiss Medical', 'escuela' => 'Instituto Nuestra Señora de Luján', 'grado' => '1.º año',
                'diagnostico' => 'Disgrafía.',
                'equipo' => ['nicolas@andar.test', 'sofia@andar.test'],
            ],
            [
                // El más chico: todavía no va al jardín ni tiene diagnóstico.
                'nombre' => 'León', 'apellido' => 'Domínguez', 'dni' => '58240671',
                'fecha_nacimiento' => '2023-02-10', 'fecha_ingreso' => '2026-09-07',
                'obra_social' => 'OSDE 310',
                'equipo' => ['catalina@andar.test', 'carolina@andar.test'],
            ],
            [
                'nombre' => 'Josefina', 'apellido' => 'Cardozo', 'dni' => '53550912',
                'fecha_nacimiento' => '2018-07-28', 'fecha_ingreso' => '2025-02-24',
                'obra_social' => 'OSECAC', 'escuela' => 'Colegio San José', 'grado' => '2.º grado',
                'diagnostico' => 'Dificultades en la lectoescritura.',
                'equipo' => ['camila@andar.test', 'florencia@andar.test'],
            ],
            [
                'nombre' => 'Agustín', 'apellido' => 'Méndez', 'dni' => '50460138',
                'fecha_nacimiento' => '2015-03-12', 'fecha_ingreso' => '2022-09-12',
                'obra_social' => 'PAMI', 'escuela' => 'Escuela N.º 12 Manuel Belgrano', 'grado' => '6.º grado',
                'diagnostico' => 'Síndrome de Down.',
                'equipo' => ['catalina@andar.test', 'micaela@andar.test', 'gabriela@andar.test', 'tamara@andar.test'],
            ],
            [
                'nombre' => 'Clara', 'apellido' => 'Figueroa', 'dni' => '55740263',
                'fecha_nacimiento' => '2020-05-19', 'fecha_ingreso' => '2025-11-03',
                'obra_social' => 'Galeno', 'escuela' => 'Colegio del Sol', 'grado' => '1.º grado',
                'diagnostico' => 'Trastorno del desarrollo del lenguaje.',
                'equipo' => ['veronica@andar.test', 'lucia@andar.test'],
            ],
            [
                'nombre' => 'Ian', 'apellido' => 'Bustos', 'dni' => '51690457',
                'fecha_nacimiento' => '2016-08-06', 'fecha_ingreso' => '2024-02-12',
                'obra_social' => 'Medifé', 'escuela' => 'Escuela N.º 23', 'grado' => '4.º grado',
                'diagnostico' => 'Trastorno del espectro autista (nivel 1).',
                'equipo' => ['mariana@andar.test', 'belen@andar.test'],
            ],
            [
                // Recién ingresada: sin equipo asignado todavía.
                'nombre' => 'Mora', 'apellido' => 'Leiva', 'dni' => '57930584',
                'fecha_nacimiento' => '2022-11-27', 'fecha_ingreso' => '2026-09-21',
                'obra_social' => 'IOMA', 'escuela' => 'Jardín Rayito de Sol', 'grado' => 'Sala de 3',
                'equipo' => [],
            ],
            [
                'nombre' => 'Facundo', 'apellido' => 'Núñez', 'dni' => '48910346',
                'fecha_nacimiento' => '2013-01-09', 'fecha_ingreso' => '2020-10-05',
                'obra_social' => 'Sancor Salud', 'escuela' => 'Escuela Secundaria N.º 4', 'grado' => '2.º año',
                'diagnostico' => 'Fobia escolar.',
                'equipo' => ['catalina@andar.test', 'martin@andar.test'],
            ],
            [
                'nombre' => 'Antonia', 'apellido' => 'Ponce', 'dni' => '54450729',
                'fecha_nacimiento' => '2019-07-15', 'fecha_ingreso' => '2025-07-14',
                'obra_social' => 'OSDE 210', 'escuela' => 'Escuela N.º 8 Juana Azurduy', 'grado' => '1.º grado',
                'diagnostico' => 'Dificultades en la motricidad gruesa.',
                'equipo' => ['ezequiel@andar.test', 'diego@andar.test'],
            ],
            [
                'nombre' => 'Benicio', 'apellido' => 'Miranda', 'dni' => '52230691',
                'fecha_nacimiento' => '2017-04-21', 'fecha_ingreso' => '2024-08-05',
                'obra_social' => 'Swiss Medical', 'escuela' => 'Colegio San José', 'grado' => '4.º grado',
                'diagnostico' => 'Parálisis cerebral leve.',
                'equipo' => ['federico@andar.test', 'carolina@andar.test', 'paula@andar.test'],
            ],
            [
                'nombre' => 'Guadalupe', 'apellido' => 'Correa', 'dni' => '56850417',
                'fecha_nacimiento' => '2021-09-03', 'fecha_ingreso' => '2026-04-27',
                'obra_social' => 'OSECAC', 'escuela' => 'Jardín N.º 903', 'grado' => 'Sala de 4',
                'diagnostico' => 'Selectividad alimentaria.',
                'equipo' => ['gabriela@andar.test', 'catalina@andar.test'],
            ],
            [
                'nombre' => 'Julián', 'apellido' => 'Vargas', 'dni' => '50250863',
                'fecha_nacimiento' => '2014-10-10', 'fecha_ingreso' => '2022-11-07',
                'obra_social' => 'IOMA', 'escuela' => 'Escuela N.º 5 Domingo F. Sarmiento', 'grado' => '6.º grado',
                'diagnostico' => 'Dislexia. Dificultades en el cálculo.',
                'equipo' => ['lucia@andar.test', 'nicolas@andar.test'],
            ],
            [
                // Sin equipo: la profesional que la atendía dejó de hacerlo y falta reasignarla.
                'nombre' => 'Zoe', 'apellido' => 'Barrios', 'dni' => '53290174',
                'fecha_nacimiento' => '2018-04-30', 'fecha_ingreso' => '2025-09-15',
                'obra_social' => 'Galeno', 'escuela' => 'Colegio del Sol', 'grado' => '3.º grado',
                'diagnostico' => 'Dificultades en la lectoescritura.',
                'equipo' => [],
            ],
            [
                'nombre' => 'Lisandro', 'apellido' => 'Godoy', 'dni' => '51950632',
                'fecha_nacimiento' => '2016-12-16', 'fecha_ingreso' => '2023-06-05',
                'obra_social' => 'Medifé', 'escuela' => 'Escuela N.º 12 Manuel Belgrano', 'grado' => '4.º grado',
                'diagnostico' => 'Trastorno del espectro autista (nivel 2).',
                'equipo' => ['agustin@andar.test', 'sofia@andar.test', 'micaela@andar.test'],
            ],
            [
                // Dada de baja: terminó el tratamiento.
                'nombre' => 'Milagros', 'apellido' => 'Blanco', 'dni' => '49320581',
                'fecha_nacimiento' => '2013-07-20', 'fecha_ingreso' => '2019-05-06',
                'obra_social' => 'OSDE 210', 'escuela' => 'Instituto Nuestra Señora de Luján', 'grado' => '1.º año',
                'diagnostico' => 'Dificultades en la lectoescritura. Alta en julio de 2026.',
                'activo' => false,
                'equipo' => ['camila@andar.test'],
            ],
            [
                // Dado de baja: cambió de centro.
                'nombre' => 'Franco', 'apellido' => 'Ledesma', 'dni' => '50980214',
                'fecha_nacimiento' => '2015-12-28', 'fecha_ingreso' => '2022-03-14',
                'obra_social' => 'IOMA',
                'diagnostico' => 'Trastorno por déficit de atención con hiperactividad.',
                'activo' => false,
                'equipo' => ['martin@andar.test', 'lucia@andar.test'],
            ],
            [
                // Dada de baja: terminó el tratamiento.
                'nombre' => 'Ámbar', 'apellido' => 'Villalba', 'dni' => '53890746',
                'fecha_nacimiento' => '2019-01-13', 'fecha_ingreso' => '2024-06-10',
                'obra_social' => 'Swiss Medical', 'escuela' => 'Escuela N.º 23', 'grado' => '2.º grado',
                'diagnostico' => 'Dislalia. Alta en marzo de 2026.',
                'activo' => false,
                'equipo' => ['paula@andar.test'],
            ],
            [
                // Dado de baja: dejó de venir.
                'nombre' => 'Ciro', 'apellido' => 'Escobar', 'dni' => '55620398',
                'fecha_nacimiento' => '2020-03-24', 'fecha_ingreso' => '2025-05-12',
                'obra_social' => 'Sancor Salud',
                'diagnostico' => 'Retraso madurativo.',
                'activo' => false,
                'equipo' => ['catalina@andar.test', 'diego@andar.test'],
            ],
        ];
    }
}
