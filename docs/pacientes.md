# Pacientes

Pantallas para cargar pacientes, ver su ficha y armar su equipo de profesionales. El listado (`/pacientes`) es una pantalla de administración, exclusiva de Dirección; se llega desde **Pacientes** en el menú de arriba (a un profesional no le aparece ahí: ve a sus propios pacientes desde el **Inicio**, ver `docs/mensajes.md`).

## Pantallas

| URL | Qué es |
|---|---|
| `/pacientes` | Listado administrativo de todos los pacientes, con el botón **Nuevo paciente**. Al lado del nombre, un globito con los mensajes sin leer (ver `docs/mensajes.md`). Exclusivo de Dirección. |
| `/pacientes/crear` | Formulario vacío para cargar uno nuevo |
| `/pacientes/12` | Ficha del paciente 12: sus datos, su equipo y el historial de mensajes (ver `docs/mensajes.md`) |
| `/pacientes/12/editar` | El mismo formulario, con los datos del paciente 12 |

En el listado administrativo, el nombre del paciente lleva **directo al formulario de edición** (no a la ficha): es una pantalla de gestión, no de consulta. La ficha se abre desde el **Inicio**, donde cada usuario ve a los pacientes que atiende. En la ficha está el botón **Editar**. Al guardar el formulario se vuelve a la ficha.

## Quién puede entrar

Depende del rol (ver `docs/roles.md`):

- **Dirección** es la única que ve el listado administrativo (`/pacientes`) y la única que puede crear y editar pacientes y armar sus equipos. Un profesional que entra por la URL recibe un error 403.
- Un **profesional** ve a los pacientes que atiende desde el **Inicio**, no desde `/pacientes`. Ve la ficha de esos pacientes, pero no los botones **Nuevo paciente** ni **Editar**.
- El **historial de mensajes** de la ficha lo ve y lo escribe solo el equipo del paciente, sea quien sea (incluida Dirección).

## Reglas del formulario

- **Obligatorios**: nombre, apellido, DNI y fecha de nacimiento. El resto es opcional.
- **DNI**: no se puede repetir. Se puede escribir con o sin puntos, pero se guarda **solo con números** (`52118304`), para que `52.118.304` y `52118304` no terminen siendo dos pacientes distintos. En pantalla siempre se muestra con puntos. Como el DNI se guarda cifrado, la base no puede detectar el repetido: lo controla el formulario, comparando contra los demás pacientes (ver `docs/cifrado.md`).
- **DNI y diagnóstico** se guardan cifrados. En las pantallas se ven normal.
- **Fecha de nacimiento**: no puede ser futura.
- **Equipo**: hay una casilla por cada usuario del sistema. Los tildados quedan como profesionales del paciente; destildar a alguien y guardar lo saca del equipo.

## Dar de baja

Un paciente **no se borra**. Al editarlo aparece la casilla **Activo**: destildarla y guardar lo da de baja. Sigue apareciendo en la lista, marcado como **Inactivo**, y conserva todos sus datos. Tildarla de nuevo lo reactiva.

Un paciente nuevo siempre nace activo (por eso la casilla no aparece al crear).

## Archivos

| Archivo | Para qué |
|---|---|
| `routes/web.php` | Las cuatro URLs (exigen sesión) |
| `app/Livewire/Pacientes/Lista.php` | Lógica del listado administrativo (`use SoloDireccion;`) |
| `app/Livewire/Pacientes/Ficha.php` | Lógica de la ficha |
| `app/Livewire/Pacientes/Formulario.php` | Lógica del formulario (crear y editar): validaciones y guardado |
| `resources/views/livewire/pacientes/` | Las tres vistas: `lista`, `ficha` y `formulario` |
| `app/Models/Paciente.php` | Modelo. Tiene `nombre_completo`, `edad` y `dni_con_puntos` para mostrar en pantalla |
| `tests/Feature/PacientesTest.php` | Pruebas |

El formulario es un solo componente para crear y editar: si la URL trae un paciente, la propiedad `pacienteId` queda con su id y se está editando; si no, se está creando.
