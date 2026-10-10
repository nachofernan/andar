# Pacientes

Pantallas para cargar pacientes, ver su ficha y armar su equipo de profesionales. El listado (`/administracion/pacientes`) es una pantalla de administración, exclusiva de Dirección; se llega desde **Administración › Pacientes** en el menú de arriba (a un profesional no le aparece: ve a sus propios pacientes desde el **Inicio**).

## Pantallas

| URL | Qué es |
|---|---|
| `/` (Inicio) | Tarjetas de resumen y la tabla **Mis pacientes** (ver más abajo) |
| `/administracion/pacientes` | Listado administrativo de los pacientes, con el botón **Nuevo paciente**. Exclusivo de Dirección. |
| `/administracion/pacientes/crear` | Formulario vacío para cargar uno nuevo |
| `/administracion/pacientes/12/editar` | El mismo formulario, con los datos del paciente 12 |
| `/pacientes/12` | Ficha del paciente 12 (ver "La ficha", más abajo) |

El listado administrativo abre mostrando solo los **activos**. Cada fila tiene el nombre (con el DNI y la edad debajo, más chicos), la fecha de **ingreso**, el **equipo** (una etiqueta por profesional, con un puntito del color de cada disciplina suya) y el estado. Tiene buscador (nombre o DNI), filtros por profesional y por estado, y se puede ordenar por paciente, ingreso y estado (ver `docs/tablas.md`). No muestra avisos de mensajes sin leer: eso es del Inicio. Clic en una fila lleva **directo al formulario de edición** (no a la ficha): es una pantalla de gestión, no de consulta. La ficha se abre desde el **Inicio**, donde cada usuario ve a los pacientes que atiende. En la ficha está el botón **Editar**. Al guardar el formulario se vuelve a la ficha.

## El Inicio: "Mis pacientes"

Es la pantalla con la que abre la app, para Dirección y para profesionales. Muestra solo los pacientes que **atiende** quien inició sesión.

- **El saludo**, en un globo: "Hola, …" y debajo cuántos mensajes sin leer hay y de cuántos pacientes (o "No tenés mensajes nuevos").
- **La tabla**: paciente (con la etiqueta "3 mensajes sin leer" si corresponde), edad, **última actividad** (fecha del último mensaje del historial y quién lo escribió; nunca el texto) y estado. Clic en una fila abre la ficha.
- Abre mostrando solo los **activos**; el filtro de estado permite ver los inactivos o todos. Tiene buscador, la casilla "Solo con mensajes sin leer" y se puede ordenar (ver `docs/tablas.md`).
- **Los pacientes con mensajes sin leer van siempre arriba**, se ordene por lo que se ordene.

## Quién puede entrar

Depende del rol (ver `docs/roles.md`):

- **Dirección** es la única que ve el listado administrativo (`/administracion/pacientes`) y la única que puede crear y editar pacientes y armar sus equipos. Un profesional que entra por la URL recibe un error 403.
- Un **profesional** ve a los pacientes que atiende desde el **Inicio**, no desde el listado administrativo. Ve la ficha de esos pacientes, pero no los botones **Nuevo paciente** ni **Editar**.
- El **historial de mensajes** de la ficha lo ve y lo escribe solo el equipo del paciente, sea quien sea (incluida Dirección).

## La ficha

Es la pantalla principal de cada paciente. Se abre desde el Inicio; arriba tiene el link **← Volver a mis pacientes**, que lleva de nuevo al Inicio. El historial ocupa el alto que queda de la ventana, así la ficha entra entera sin tener que scrollear la página (los mensajes scrollean por dentro).

- **Cabecera**: un círculo con las iniciales, el nombre y el estado; debajo el DNI, la edad, el grado y la escuela; el **equipo**, con las etiquetas de color de las disciplinas de cada profesional; y a la derecha nacimiento, ingreso y obra social.
- **Historial del equipo**: ocupa el lugar principal, con forma de chat y el cuadro de escribir abajo. Es también la bitácora de los cambios del paciente. Todo el detalle está en `docs/mensajes.md`.
- **Columna de la derecha**: el **Diagnóstico** y los **Archivos** del historial.

### Cambiar el diagnóstico desde la ficha

El diagnóstico es el único dato que se cambia desde la ficha, y lo puede cambiar **cualquier profesional del equipo** del paciente: en la tarjeta Diagnóstico aprieta **Editar**, escribe y guarda. El cambio queda anotado en el historial, con lo que decía antes y lo que dice ahora.

Quien ve la ficha sin ser del equipo (Dirección en un paciente que no atiende) ve el diagnóstico pero no el botón. Dirección lo puede cambiar igual desde el formulario de administración, y también queda anotado.

### Los cambios quedan en el historial

Todo cambio en los datos de un paciente ya cargado (desde el formulario de administración o el diagnóstico desde la ficha) pasa por `Paciente::actualizar()`, que guarda y deja una entrada en el historial. Si alguna vez se suma otra pantalla que modifique pacientes, tiene que usar ese método para no saltearse la bitácora.

## El formulario

A la izquierda, los datos en tres secciones: **Datos personales**, **Escolaridad y cobertura** y **En el centro** (ingreso y diagnóstico). A la derecha, un panel fijo con el **Equipo** (un renglón por profesional, con sus disciplinas en color y un contador de elegidos), el **Estado** (solo al editar) y los botones. Cómo está armado: `docs/estilos.md`, sección Formularios.

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
| `routes/web.php` | Las URLs (exigen sesión). Las de administración están en el grupo `administracion` |
| `app/Livewire/Inicio.php` y `resources/views/livewire/inicio.blade.php` | El Inicio: tarjetas y tabla "Mis pacientes" |
| `app/Livewire/Pacientes/Lista.php` | Lógica del listado administrativo (`use SoloDireccion;`) |
| `app/Livewire/Pacientes/Ficha.php` | Lógica de la ficha: permisos, cambio de diagnóstico y lista de archivos |
| `app/Livewire/Pacientes/Formulario.php` | Lógica del formulario (crear y editar): validaciones y guardado |
| `resources/views/livewire/pacientes/` | Las tres vistas: `lista`, `ficha` y `formulario` |
| `app/Models/Paciente.php` | Modelo. Tiene `nombre_completo`, `edad`, `dni_con_puntos` e `iniciales` para mostrar en pantalla, y `actualizar()` para modificarlo dejando registro |
| `tests/Feature/PacientesTest.php`, `tests/Feature/InicioTest.php` | Pruebas |

El formulario es un solo componente para crear y editar: si la URL trae un paciente, la propiedad `pacienteId` queda con su id y se está editando; si no, se está creando.
