# Mensajes (historial del equipo)

Cada paciente tiene un **historial**: el lugar donde los profesionales que lo atienden se escriben entre sí sobre ese paciente. Es lo principal de la ficha (`/pacientes/12`) y tiene forma de **chat**: lo más viejo arriba, lo más nuevo abajo y el cuadro para escribir al pie.

El historial es también la **bitácora** del paciente: cada vez que alguien cambia uno de sus datos, la app anota sola una entrada con lo que había antes y lo que quedó (ver "La bitácora", más abajo).

## Quién puede verlo y escribir

**Solo el equipo del paciente**, es decir, los profesionales tildados en su formulario.

- Quien es del equipo ve todos los mensajes, puede escribir y puede bajar los archivos.
- Quien no es del equipo ve la ficha, pero en el lugar del historial solo le aparece el aviso "Solo el equipo de este paciente puede ver el historial". Los mensajes ni siquiera se le envían al navegador, y si intenta bajar un archivo recibe un error.

Esto vale **también para Dirección**: ve la ficha de cualquier paciente, pero el historial solo de los pacientes en los que está en el equipo. Si necesita ver un caso, se agrega al equipo desde **Editar** (solo Dirección puede armar equipos). Ver `docs/roles.md`.

## Cómo se usa

- **Escribir**: abajo de todo. Se escribe el texto, se elige el tipo y se aprieta **Enviar**. Queda a nombre del usuario que inició sesión, con la fecha y hora del momento, y el chat baja solo hasta el mensaje nuevo.
- **Tipos**: Mensaje, Informe de evaluación, Indicación y Nota de sesión. "Mensaje" es el común y no lleva etiqueta; los otros tres se muestran con una etiqueta verde. Los informes, por ahora, son simplemente un tipo de mensaje.
- **Filtro**: los botones de arriba (Todos, Mensaje, Informe… y Actualización) muestran solo los de ese tipo.
- **Orden**: como un chat, del más viejo (arriba) al más nuevo (abajo), separados por día ("hoy", "ayer" o la fecha). El historial tiene su propio scroll y abre parado en lo último.
- **Cómo se ve cada mensaje**: los míos van a la derecha, en violeta clarito; los de los demás, a la izquierda. Cada uno lleva un círculo con las iniciales de quien lo escribió (pintado con el color de su primera disciplina), su nombre, una etiqueta por cada disciplina, la hora, el tipo, el texto (respetando los renglones) y sus archivos.

## La bitácora (entradas automáticas)

Cuando cambia un dato del paciente, queda una entrada en su historial, escrita por la app. Sirve de auditoría: se puede ver qué cambió, quién lo cambió y cuándo.

- **Qué registra**: nombre, apellido, DNI, fecha de nacimiento, ingreso, obra social, escuela, grado, diagnóstico, estado (activo/inactivo) y los cambios de equipo (quién se sumó y quién salió).
- **Qué muestra**: quién hizo el cambio, la hora y, por cada dato, **lo anterior y lo nuevo** ("Grado: 3.º → 4.º"). Los textos largos, como el diagnóstico, van en dos renglones (Antes / Ahora).
- **Una entrada por guardado**: si en el formulario se cambian tres datos, queda una sola entrada con los tres. Si se guarda sin cambiar nada, no queda ninguna. Cargar un paciente nuevo tampoco deja entrada.
- **Se ve distinto**: no es un globo de chat sino un recuadro gris, centrado y más chico, con la etiqueta **Actualización**.
- Igual que un mensaje, **no se edita ni se borra**, y a los demás del equipo les cuenta como **sin leer**.
- Como son parte del historial, las ve **solo el equipo**.

Por dentro, una entrada es un mensaje más, de tipo `actualizacion`. Su texto es la lista de cambios (en formato JSON), y se guarda **cifrado** como cualquier otro texto de mensaje: el diagnóstico o el DNI anteriores no quedan a la vista en la base. La arma `Paciente::actualizar()`, que es el único camino para modificar un paciente ya cargado: lo usan el formulario de administración y el cambio de diagnóstico desde la ficha.

Ese tipo lo escribe solo la app: está en `Mensaje::TIPOS_AUTOMATICOS`, no aparece en el desplegable para escribir y, si alguien lo manda igual, se rechaza.

Para registrar un dato más: agregarlo a `Paciente::DATOS_REGISTRADOS` (columna => nombre que se muestra).

## Archivos adjuntos

Al escribir un mensaje se le pueden adjuntar archivos con el botón **📎 Adjuntar**.

- **Tipos aceptados**: PDF, Word (`.doc`, `.docx`), Excel (`.xls`, `.xlsx`) e imágenes (`.jpg`, `.png`). El explorador de archivos muestra solo esos tipos.
- **Límites**: hasta 5 archivos por mensaje, de hasta 10 MB cada uno.
- **Se van sumando**: cada vez que se elige un archivo, se agrega a los ya elegidos (no los reemplaza). Los elegidos aparecen como etiquetas arriba de los botones, cada una con una **✕** para sacarla antes de enviar.
- **El texto sigue siendo obligatorio**: no se puede mandar un archivo sin escribir al menos una línea.
- Cada adjunto aparece debajo del texto del mensaje como un link con 📎, su nombre y su tamaño. Al hacer clic se **descarga** (no se abre en el navegador).
- En la columna de la derecha de la ficha, la tarjeta **Archivos** junta todos los adjuntos del historial, con quién lo subió y cuándo. Solo aparece si hay alguno.
- Igual que los mensajes, **un adjunto no se puede borrar ni reemplazar** una vez enviado.
- Se guardan **cifrados** y con el nombre oculto (ver `docs/cifrado.md`).

Para cambiar los tipos o los límites: constantes `EXTENSIONES_ADJUNTO`, `MAX_ADJUNTOS` y `MAX_KB_ADJUNTO` en `app/Models/Mensaje.php`. Si se sube el tamaño por encima de 12 MB hay que subir también el límite de subida de Livewire.

Por dentro: el selector de archivos escribe en una propiedad "de paso" (`nuevos`); apenas termina de subir, `updatedNuevos()` pasa esos archivos a la lista `archivos` y la deja vacía. Por eso elegir de nuevo suma en vez de pisar.

## Mensajes sin leer

La app avisa cuando hay mensajes que todavía no viste. No manda mails ni notificaciones del navegador: los avisos están dentro de la app.

**Qué cuenta como "sin leer"**: un mensaje (o una entrada de la bitácora) de un paciente de tu equipo, hecho por otra persona, posterior a la última vez que abriste la ficha de ese paciente. Tus propios mensajes nunca cuentan. Tampoco cuentan los de pacientes **inactivos** (dados de baja): no prenden el puntito ni suman en el saludo.

**Cuándo pasa a leído**: al **abrir la ficha** del paciente. Entrar a la ficha marca todo su historial como leído; no hay que apretar nada.

**Dónde se ve**:

| Lugar | Qué muestra |
|---|---|
| Menú de arriba | Un puntito rojo sobre **Mis pacientes** si hay algún mensaje sin leer. No dice cuántos: el detalle está en el Inicio. |
| Inicio | El saludo de arriba ("Tenés 3 mensajes sin leer de 2 pacientes" o "No tenés mensajes nuevos") y la tabla **Mis pacientes**: al lado del nombre de cada paciente que tiene mensajes sin leer, una etiqueta violeta clarita con la cantidad ("3 mensajes sin leer"). Esos pacientes aparecen siempre arriba de la tabla. |
| Historial | Los mensajes que no habías visto aparecen resaltados y con la etiqueta **Nuevo**, durante esa visita. |

A tener en cuenta:

- Los números **no se actualizan solos**: cambian al cargar o cambiar de pantalla. Si alguien escribe mientras tenés una pantalla abierta, lo ves al recargar.
- Quien se suma al equipo de un paciente ve todo su historial como sin leer hasta que abre la ficha.
- Si te sacan del equipo de un paciente, sus mensajes dejan de contarte.

Por dentro: en la tabla `paciente_user` (la que une a cada profesional con cada paciente) se guarda `mensajes_vistos_at`, la fecha y hora de la última vez que ese profesional abrió ese historial. La cuenta la hace `User::mensajesSinLeer()`, y es la única fuente de todos los avisos.

## Reglas

- El texto es obligatorio y admite hasta 5000 caracteres.
- **Un mensaje enviado no se edita ni se borra.** Queda como registro. Si alguien se equivoca, escribe otro mensaje aclarando.
- El texto y los archivos se guardan **cifrados** (ver `docs/cifrado.md`). Por eso todavía no hay un buscador dentro de los mensajes.
- Tampoco se puede borrar desde la base un paciente o un usuario que tenga mensajes: la base lo impide para no dejar mensajes huérfanos.

## Para cambiar los tipos de mensaje

Están en un solo lugar: la lista `TIPOS` de `app/Models/Mensaje.php`. A la izquierda va lo que se guarda en la base y a la derecha lo que se muestra. Para renombrar uno se cambia el texto de la derecha; para sumar uno se agrega un renglón.

## Archivos del código

| Archivo | Para qué |
|---|---|
| `database/migrations/2026_10_09_120000_create_mensajes_table.php` | Crea la tabla `mensajes` |
| `database/migrations/2026_10_09_000004_create_paciente_user_table.php` | Tiene `mensajes_vistos_at` (para los no leídos) |
| `app/Models/Mensaje.php` | Modelo: tipos (y cuáles son automáticos), `->cambios` de una entrada de bitácora, límites de adjuntos, cifrado del texto y de los archivos, `->paciente` y `->autor` |
| `app/Models/Paciente.php` | `->mensajes`, `esDelEquipo($usuario)` (la regla de quién accede) y `actualizar()`, que arma las entradas de la bitácora |
| `app/Models/User.php` | `mensajesSinLeer()`, la cuenta de no leídos |
| `app/Livewire/Pacientes/Mensajes.php` | Lógica: enviar, adjuntar, filtrar, listar y marcar como leído |
| `resources/views/livewire/pacientes/mensajes.blade.php` | La vista del chat |
| `app/Livewire/Pacientes/Ficha.php` y su vista | La ficha: cabecera, diagnóstico y la tarjeta Archivos |
| `resources/views/components/avatar.blade.php` | El círculo con las iniciales de quien escribe |
| `app/Http/Controllers/AdjuntoController.php` | Descarga de un adjunto (`/adjuntos/{id}`) |
| `app/Livewire/Inicio.php` y `resources/views/livewire/inicio.blade.php` | Página de inicio: "Mis pacientes" y sus mensajes sin leer |
| `resources/views/layouts/app.blade.php` | El puntito rojo del menú |
| `database/seeders/MensajeSeeder.php` | Mensajes de ejemplo (sin adjuntos) |
| `tests/Feature/MensajesTest.php`, `AdjuntosTest.php`, `MensajesSinLeerTest.php` | Pruebas |

## Pendiente

Buscador dentro de los mensajes, avisos en vivo o por mail y paginación del historial no están hechos ni decididos. El chat tampoco se actualiza solo: si otra persona escribe mientras tenés la ficha abierta, lo ves al recargar.
