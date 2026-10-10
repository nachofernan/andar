# Roles y permisos

Qué puede hacer cada usuario en la app. Hay **dos roles** y cada usuario tiene exactamente uno.

| Rol | Quién es |
|---|---|
| **Dirección** | Las cabezas del centro. Cargan y organizan todo. |
| **Profesional** | Quien atiende pacientes. Ve solo lo de sus pacientes. |

## Qué puede hacer cada uno

| | Dirección | Profesional |
|---|---|---|
| Entrar a **Administración** (todo lo que cuelga de `/administracion`) | Sí | No (ni aparece en el menú) |
| Listado administrativo de pacientes | Sí | No |
| Ver a "mis pacientes" en el Inicio | Los que atiende | Los que atiende |
| Ver la ficha de un paciente (datos y diagnóstico) | La de todos | Solo la de los que atiende |
| Crear y editar pacientes, darlos de baja | Sí | No |
| Cambiar el diagnóstico desde la ficha | Solo si está en su equipo (si no, desde el formulario) | Solo si está en su equipo |
| Armar el equipo de un paciente | Sí | No |
| Historial de mensajes y archivos de un paciente | **Solo si está en su equipo** | Solo si está en su equipo |
| Profesionales y disciplinas: listar, crear y editar | Sí | No |
| Cambiar los propios datos y la contraseña (Mi perfil) | Sí | Sí |

## Lo que hay que saber

- **Dirección no ve todos los historiales.** Ve la ficha de cualquier paciente, pero los mensajes y los archivos solo de los pacientes en los que está en el equipo, igual que un profesional. Si necesita ver un caso, se agrega al equipo de ese paciente desde **Editar**: así queda a la vista de todos que forma parte.
- **Un profesional no puede sumarse solo a un equipo**: los equipos los arma Dirección.
- Si un profesional intenta entrar por la dirección (URL) a algo que no le corresponde, ve un error **403** ("no autorizado").
- Un paciente **sin equipo** solo lo ve Dirección.

## Cómo se le da el rol a un usuario

En **Administración › Profesionales y disciplinas**, al crear o editar un profesional, con la casilla **Es administrador**: tildada es Dirección; sin tildar, Profesional. Un usuario nuevo viene como Profesional.

**No se puede dejar al centro sin nadie de Dirección**: si se intenta pasar a Profesional a la única persona de Dirección, la app no lo permite y avisa. Primero hay que darle Dirección a otra persona.

## Cómo está hecho

Los roles usan el paquete **Spatie Permission** (solo roles; no se usan "permisos" sueltos).

- Los dos roles se crean en una migración, para que existan en toda instalación.
- La lista de roles y sus nombres está en `User::ROLES`. `$usuario->esDireccion()` responde si es de Dirección y `$usuario->rol` devuelve su rol.
- Las pantallas que son solo de Dirección llevan `use SoloDireccion;`. Ese agregado comprueba el rol cada vez que la pantalla se abre y también en cada acción (guardar, editar), así no alcanza con esconder un botón.
- Las dos reglas sobre pacientes están en `app/Models/Paciente.php`:
  - `puedeVerlo($usuario)`: quién ve la ficha (Dirección o el equipo).
  - `esDelEquipo($usuario)`: quién ve el historial, baja archivos y cambia el diagnóstico desde la ficha (solo el equipo).

## Archivos

| Archivo | Para qué |
|---|---|
| `database/migrations/2026_10_09_130000_create_roles_iniciales.php` | Crea los dos roles |
| `app/Models/User.php` | `ROLES`, `esDireccion()` y `rol` |
| `app/Models/Paciente.php` | `puedeVerlo()` y `esDelEquipo()` |
| `app/Livewire/Concerns/SoloDireccion.php` | El control "solo Dirección" que usan las pantallas de gestión |
| `app/Livewire/Pacientes/Lista.php`, `Pacientes/Formulario.php`, `Usuarios/Lista.php`, `Usuarios/Formulario.php`, `Disciplinas/Formulario.php` | Pantallas solo de Dirección (las de `/administracion`) |
| `app/Livewire/Inicio.php` y `Pacientes/Ficha.php` | Filtran o cortan según el rol y el equipo |
| `app/Livewire/Perfil.php` | "Mi perfil": la puede abrir cualquier rol, no lleva `SoloDireccion` |
| `resources/views/layouts/app.blade.php` | El menú, distinto según el rol |
| `database/factories/UserFactory.php` | En los tests: `create()` da un profesional y `direccion()` uno de Dirección |
| `tests/Feature/RolesTest.php` | Pruebas de los permisos |

## Para agregar un rol más adelante

Por ejemplo "Administración": se agrega a `User::ROLES`, se crea con una migración nueva (como la de arriba) y se decide pantalla por pantalla qué puede hacer. No hay nada automático: cada regla está escrita en los lugares de la tabla de arriba.

## Pendiente

Sacar el login precargado, antes de usar datos reales (ver `docs/login.md`). El cambio de contraseña ya existe, en **Mi perfil** (`docs/perfil.md`).
