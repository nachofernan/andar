# Mensajes (historial del equipo)

Cada paciente tiene un **historial de mensajes**: el lugar donde los profesionales que lo atienden se escriben entre sí sobre ese paciente. Está al final de la ficha (`/pacientes/12`), en el bloque **Historial del equipo**.

## Quién puede verlo y escribir

**Solo el equipo del paciente**, es decir, los profesionales tildados en su formulario.

- Quien es del equipo ve todos los mensajes, puede escribir y puede bajar los archivos.
- Quien no es del equipo ve la ficha, pero en el bloque del historial solo le aparece el aviso "Solo el equipo de este paciente puede ver el historial". Los mensajes ni siquiera se le envían al navegador, y si intenta bajar un archivo recibe un error.

Esto vale **también para Dirección**: ve la ficha de cualquier paciente, pero el historial solo de los pacientes en los que está en el equipo. Si necesita ver un caso, se agrega al equipo desde **Editar** (solo Dirección puede armar equipos). Ver `docs/roles.md`.

## Cómo se usa

- **Escribir**: se escribe el texto, se elige el tipo y se aprieta **Enviar**. Queda a nombre del usuario que inició sesión, con la fecha y hora del momento.
- **Tipos**: Mensaje, Informe de evaluación, Indicación y Nota de sesión. "Mensaje" es el común y no lleva etiqueta; los otros tres se muestran con una etiqueta verde. Los informes, por ahora, son simplemente un tipo de mensaje.
- **Filtro**: los botones de arriba de la lista (Todos, Mensaje, Informe…) muestran solo los de ese tipo.
- **Orden**: el más nuevo arriba.
- De cada mensaje se ve quién lo escribió, sus disciplinas (en una sola etiqueta, separadas por un punto: "Psicología · Psicopedagogía"), el tipo, la fecha y hora, el texto (respetando los renglones) y sus archivos.

## Archivos adjuntos

Al escribir un mensaje se le pueden adjuntar archivos con **Adjuntar archivos**.

- **Tipos aceptados**: PDF, Word (`.doc`, `.docx`), Excel (`.xls`, `.xlsx`) e imágenes (`.jpg`, `.png`).
- **Límites**: hasta 5 archivos por mensaje, de hasta 10 MB cada uno.
- **El texto sigue siendo obligatorio**: no se puede mandar un archivo sin escribir al menos una línea.
- Cada adjunto aparece debajo del texto del mensaje como un link con 📎, su nombre y su tamaño. Al hacer clic se **descarga** (no se abre en el navegador).
- Arriba de los mensajes, el bloque **Archivos del paciente** junta todos los adjuntos del historial, con quién lo subió y cuándo. Solo aparece si hay alguno.
- Igual que los mensajes, **un adjunto no se puede borrar ni reemplazar**.
- Se guardan **cifrados** y con el nombre oculto (ver `docs/cifrado.md`).

Para cambiar los tipos o los límites: constantes `EXTENSIONES_ADJUNTO`, `MAX_ADJUNTOS` y `MAX_KB_ADJUNTO` en `app/Models/Mensaje.php`. Si se sube el tamaño por encima de 12 MB hay que subir también el límite de subida de Livewire.

## Mensajes sin leer

La app avisa cuando hay mensajes que todavía no viste. No manda mails ni notificaciones del navegador: los avisos están dentro de la app.

**Qué cuenta como "sin leer"**: un mensaje de un paciente de tu equipo, escrito por otra persona, posterior a la última vez que abriste la ficha de ese paciente. Tus propios mensajes nunca cuentan.

**Cuándo pasa a leído**: al **abrir la ficha** del paciente. Entrar a la ficha marca todo su historial como leído; no hay que apretar nada.

**Dónde se ve**:

| Lugar | Qué muestra |
|---|---|
| Menú de arriba | Un puntito rojo sobre **Inicio** si hay algún mensaje sin leer. No dice cuántos: el detalle está en el Inicio. |
| Inicio | Las tarjetas de resumen (pacientes con mensajes sin leer y total de mensajes sin leer) y la tabla **Mis pacientes**: al lado del nombre de cada paciente que tiene mensajes sin leer, una etiqueta violeta clarita con la cantidad ("3 mensajes sin leer"). Esos pacientes aparecen siempre arriba de la tabla. |
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
| `app/Models/Mensaje.php` | Modelo: tipos, límites de adjuntos, cifrado del texto y de los archivos, `->paciente` y `->autor` |
| `app/Models/Paciente.php` | `->mensajes` y `esDelEquipo($usuario)`, la regla de quién accede |
| `app/Models/User.php` | `mensajesSinLeer()`, la cuenta de no leídos |
| `app/Livewire/Pacientes/Mensajes.php` | Lógica: enviar, adjuntar, filtrar, listar y marcar como leído |
| `resources/views/livewire/pacientes/mensajes.blade.php` | La vista del bloque |
| `app/Http/Controllers/AdjuntoController.php` | Descarga de un adjunto (`/adjuntos/{id}`) |
| `app/Livewire/Inicio.php` y `resources/views/livewire/inicio.blade.php` | Página de inicio: "Mis pacientes" y sus mensajes sin leer |
| `resources/views/layouts/app.blade.php` | El puntito rojo del menú |
| `database/seeders/MensajeSeeder.php` | Mensajes de ejemplo (sin adjuntos) |
| `tests/Feature/MensajesTest.php`, `AdjuntosTest.php`, `MensajesSinLeerTest.php` | Pruebas |

## Pendiente

Buscador dentro de los mensajes, avisos en vivo o por mail y paginación del historial no están hechos ni decididos.
