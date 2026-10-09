# Modelos y base de datos

Qué se guarda en la base y cómo se relaciona. Por ahora hay tres cosas: **profesionales**, **disciplinas** y **pacientes**. Las pantallas para cargarlos están explicadas en `docs/disciplinas-y-usuarios.md` y `docs/pacientes.md`.

## Cómo se relacionan

```
disciplinas ──< users (profesionales) >──< pacientes
                        (a través de paciente_user)
```

- Una **disciplina** tiene muchos profesionales. Cada profesional tiene **una sola** disciplina (o ninguna).
- Un **paciente** tiene varios profesionales (su equipo) y un profesional atiende a varios pacientes.

## Tablas

### `disciplinas`

| Campo | Qué guarda |
|---|---|
| `nombre` | Nombre de la disciplina. No se puede repetir. |

### `users` (los profesionales)

Es la tabla de usuarios de Laravel: quien inicia sesión es un profesional. Se le agregó:

| Campo | Qué guarda |
|---|---|
| `disciplina_id` | A qué disciplina pertenece. Opcional. Si se borra la disciplina, el profesional queda sin disciplina (no se borra). |

El resto (`name`, `email`, `password`) es lo que ya había para el login.

### `pacientes`

| Campo | Obligatorio | Qué guarda |
|---|---|---|
| `nombre` | Sí | |
| `apellido` | Sí | |
| `dni` | Sí | No se puede repetir. Se guarda como texto, solo con números (sin puntos). |
| `fecha_nacimiento` | Sí | |
| `fecha_ingreso` | No | Cuándo empezó a venir al espacio. |
| `obra_social` | No | Texto libre (ej. "OSDE 210"). |
| `escuela` | No | Texto libre. |
| `grado` | No | Texto libre (ej. "4.º grado"). |
| `diagnostico` | No | Texto largo. |
| `activo` | — | Arranca en "sí". Se pone en "no" para dar de baja sin borrar. |

### `paciente_user`

Tabla intermedia: cada fila dice "este profesional atiende a este paciente". El mismo par no se puede repetir. Si se borra el paciente o el profesional, se borran sus filas acá.

## Archivos

| Archivo | Para qué |
|---|---|
| `database/migrations/2026_10_09_000001_create_disciplinas_table.php` | Crea `disciplinas` |
| `database/migrations/2026_10_09_000002_add_disciplina_id_to_users_table.php` | Agrega la disciplina al profesional |
| `database/migrations/2026_10_09_000003_create_pacientes_table.php` | Crea `pacientes` |
| `database/migrations/2026_10_09_000004_create_paciente_user_table.php` | Crea la tabla intermedia |
| `app/Models/Disciplina.php` | Modelo. `->profesionales` |
| `app/Models/Paciente.php` | Modelo. `->profesionales` |
| `app/Models/User.php` | Modelo del profesional. `->disciplina` y `->pacientes` |
| `database/seeders/DatabaseSeeder.php` | Datos de ejemplo |
| `tests/Feature/ModelosTest.php` | Pruebas de las relaciones |

## Ejemplos de uso

```php
$catalina->disciplina->nombre;          // "Psicología"
$paciente->profesionales;               // el equipo del paciente
$catalina->pacientes;                   // los pacientes que atiende
$paciente->profesionales()->attach($catalina);   // sumarla al equipo
$paciente->profesionales()->detach($catalina);   // sacarla del equipo

// Datos calculados del paciente (no son columnas, los arma el modelo):
$paciente->nombre_completo;             // "González, Tomás"
$paciente->edad;                        // 9 (años cumplidos hoy)
$paciente->dni_con_puntos;              // "52.118.304"
```

## Datos de ejemplo

`php artisan migrate:fresh --seed` **borra toda la base** y la vuelve a crear con las disciplinas Psicología, Psicopedagogía y Fonoaudiología, y la usuaria `catalina@andar.test` / `password` con disciplina Psicología. No carga pacientes.
