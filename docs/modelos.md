# Modelos y base de datos

Qué se guarda en la base y cómo se relaciona. Por ahora hay cuatro cosas: **profesionales**, **disciplinas**, **pacientes** y **mensajes**. Las pantallas están explicadas en `docs/disciplinas-y-usuarios.md`, `docs/pacientes.md` y `docs/mensajes.md`.

## Cómo se relacionan

```
disciplinas >──< users (profesionales) >──< pacientes
    (a través de disciplina_user)   (a través de paciente_user)

users (autor) ──< mensajes >── pacientes
```

- Una **disciplina** tiene muchos profesionales, y un profesional puede tener **varias disciplinas**, una o ninguna.
- Un **paciente** tiene varios profesionales (su equipo) y un profesional atiende a varios pacientes.
- Un **mensaje** es de un solo paciente y lo escribió un solo profesional. Un paciente tiene muchos mensajes.

## Tablas

### `disciplinas`

| Campo | Qué guarda |
|---|---|
| `nombre` | Nombre de la disciplina. No se puede repetir. |

### `users` (los profesionales)

Es la tabla de usuarios de Laravel, tal cual viene (`name`, `email`, `password`): quien inicia sesión es un profesional. Sus disciplinas no están acá, sino en `disciplina_user`.

### `disciplina_user`

Tabla intermedia: cada fila dice "este profesional tiene esta disciplina". El mismo par no se puede repetir. Si se borra la disciplina o el profesional, se borran sus filas acá (el otro no se borra: al profesional solo se le quita esa disciplina).

### `pacientes`

| Campo | Obligatorio | Qué guarda |
|---|---|---|
| `nombre` | Sí | |
| `apellido` | Sí | |
| `dni` | Sí | **Cifrado.** Solo con números (sin puntos). No se puede repetir, pero eso lo controla el formulario, no la base. |
| `fecha_nacimiento` | Sí | |
| `fecha_ingreso` | No | Cuándo empezó a venir al espacio. |
| `obra_social` | No | Texto libre (ej. "OSDE 210"). |
| `escuela` | No | Texto libre. |
| `grado` | No | Texto libre (ej. "4.º grado"). |
| `diagnostico` | No | **Cifrado.** Texto largo. |
| `activo` | — | Arranca en "sí". Se pone en "no" para dar de baja sin borrar. |

Los campos cifrados se ven ilegibles si se mira la base directamente; desde el código se leen normal. Qué implica y cómo cuidar la clave: `docs/cifrado.md`.

### `paciente_user`

Tabla intermedia: cada fila dice "este profesional atiende a este paciente". El mismo par no se puede repetir. Si se borra el paciente o el profesional, se borran sus filas acá.

Guarda además `mensajes_vistos_at`: hasta cuándo vio ese profesional el historial de mensajes de ese paciente (vacío = nunca lo abrió). Con eso se calculan los mensajes sin leer (ver `docs/mensajes.md`).

### `mensajes`

El historial de comunicación del equipo sobre cada paciente (ver `docs/mensajes.md`).

| Campo | Qué guarda |
|---|---|
| `paciente_id` | De qué paciente es. |
| `user_id` | Quién lo escribió. |
| `tipo` | `mensaje`, `informe`, `indicacion` o `nota`. La lista y sus nombres están en `Mensaje::TIPOS`. |
| `cuerpo` | **Cifrado.** El texto del mensaje. |
| `created_at` | Fecha y hora de envío. |

La base no deja borrar un paciente o un usuario que tenga mensajes.

### `media`

Tabla del paquete Spatie Media Library: una fila por cada archivo subido. Hoy la usan los **adjuntos de los mensajes**: cada fila apunta a su mensaje (`model_type` / `model_id`).

Como los archivos van cifrados, varias columnas no dicen lo que su nombre sugiere: `file_name` es un nombre al azar terminado en `.enc`, `name` siempre dice "adjunto", y `mime_type` y `size` corresponden al archivo ya cifrado. Los datos reales están en `custom_properties`: `nombre` (cifrado), `tipo` y `tamano`. Ver `docs/cifrado.md`.

## Archivos

| Archivo | Para qué |
|---|---|
| `database/migrations/2026_10_09_000001_create_disciplinas_table.php` | Crea `disciplinas` |
| `database/migrations/2026_10_09_000002_create_disciplina_user_table.php` | Crea la tabla intermedia de disciplinas por profesional |
| `database/migrations/2026_10_09_000003_create_pacientes_table.php` | Crea `pacientes` |
| `database/migrations/2026_10_09_000004_create_paciente_user_table.php` | Crea la tabla intermedia |
| `database/migrations/2026_10_09_111213_create_media_table.php` | Crea `media` (Media Library) |
| `database/migrations/2026_10_09_120000_create_mensajes_table.php` | Crea `mensajes` |
| `app/Models/Mensaje.php` | Modelo. `->paciente` y `->autor` |
| `app/Models/Disciplina.php` | Modelo. `->profesionales` |
| `app/Models/Paciente.php` | Modelo. `->profesionales`, `->mensajes` y `esDelEquipo($usuario)` |
| `app/Models/User.php` | Modelo del profesional. `->disciplinas` y `->pacientes` |
| `database/seeders/DatabaseSeeder.php` | Datos de ejemplo: llama a los tres seeders de abajo, en orden |
| `database/seeders/DisciplinaSeeder.php` | Disciplinas de ejemplo |
| `database/seeders/EquipoSeeder.php` | Profesionales de ejemplo |
| `database/seeders/PacienteSeeder.php` | Pacientes de ejemplo con su equipo |
| `database/seeders/MensajeSeeder.php` | Mensajes de ejemplo |
| `tests/Feature/SeedersTest.php` | Prueba de que los datos de ejemplo se cargan bien |
| `tests/Feature/ModelosTest.php` | Pruebas de las relaciones |

## Ejemplos de uso

```php
$lucia->disciplinas;                    // sus disciplinas, por orden alfabético
$lucia->disciplinas->pluck('nombre');   // ["Psicología", "Psicopedagogía"]
$psicologia->profesionales;             // quiénes tienen esa disciplina
$lucia->disciplinas()->attach($fono);   // sumarle una disciplina
$paciente->profesionales;               // el equipo del paciente
$catalina->pacientes;                   // los pacientes que atiende
$paciente->profesionales()->attach($catalina);   // sumarla al equipo
$paciente->profesionales()->detach($catalina);   // sacarla del equipo
$paciente->esDelEquipo($catalina);      // true o false: ¿lo atiende?
$paciente->mensajes;                    // su historial
$mensaje->autor->name;                  // quién lo escribió
$mensaje->getMedia('adjuntos');         // sus archivos adjuntos
$catalina->mensajesSinLeer();           // [id de paciente => cantidad sin leer]

// Datos calculados del paciente (no son columnas, los arma el modelo):
$paciente->nombre_completo;             // "González, Tomás"
$paciente->edad;                        // 9 (años cumplidos hoy)
$paciente->dni_con_puntos;              // "52.118.304"
```

## Datos de ejemplo

`php artisan migrate:fresh --seed` **borra toda la base** y la vuelve a crear con datos de ejemplo. Sirven solo para probar las pantallas mientras se desarrolla. **Son inventados** (salvo Catalina): ningún paciente, DNI ni diagnóstico es real.

Los datos están escritos a mano en los seeders, no se generan al azar: cada vez que se corre el comando queda exactamente la misma base. Para cambiar o agregar un dato, se edita el archivo y se vuelve a correr.

| Seeder | Qué carga |
|---|---|
| `DisciplinaSeeder` | 6 disciplinas: Psicología, Psicopedagogía, Fonoaudiología, Terapia Ocupacional, Psicomotricidad y Acompañamiento Terapéutico. |
| `EquipoSeeder` | 8 usuarios: Catalina es de **Dirección** y los otros siete son **profesionales** (ver `docs/roles.md`). Todos entran con la contraseña `password` y un mail del estilo `nombre@andar.test` (el de siempre: `catalina@andar.test`). Hay una profesional con dos disciplinas (`lucia@andar.test`: Psicología y Psicopedagogía) y una usuaria sin ninguna (`romina@andar.test`). |
| `PacienteSeeder` | 15 pacientes de entre 3 y 13 años, con su equipo. Incluye casos distintos a propósito: uno solo con los datos obligatorios, uno sin equipo, dos dados de baja, un diagnóstico largo y un DNI de siete dígitos. |

| `MensajeSeeder` | 10 mensajes en el historial de 4 pacientes (Tomás González, Benjamín Fernández, Mía Romero y Mateo Martínez), de distintos autores y tipos, con fechas fijas y sin archivos adjuntos. Deja a Catalina con el historial de Tomás ya visto hasta el 5/10, así que entra con 3 mensajes sin leer (1 en Tomás y 2 en Benjamín). |

El orden importa (el equipo necesita las disciplinas, los pacientes al equipo y los mensajes a los pacientes); lo maneja `DatabaseSeeder`.
