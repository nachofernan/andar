# Profesionales y disciplinas

Los usuarios (los profesionales) y las disciplinas del centro se administran desde una misma pantalla: **Administración › Profesionales y disciplinas** (`/administracion/profesionales`). Se pueden **listar, crear y editar**. No se puede borrar.

## Quién puede entrar

**Solo Dirección.** A un profesional no le aparece "Administración" en el menú y, si entra por la dirección, ve un error 403 (ver `docs/roles.md`).

## La pantalla

Es solo un listado, en dos partes:

- **A la izquierda, la tabla de profesionales**:
  - **Nombre**, con el correo debajo, más chico. Quien es de Dirección lleva al lado la etiqueta **Admin** (no hay columna de rol).
  - **Disciplinas**, una etiqueta por cada una, con su color.
  - **Pacientes**: cuántos atiende (cuenta activos e inactivos).
  - Tiene buscador (nombre o correo), filtro por disciplina y se ordena por nombre o por cantidad de pacientes (ver `docs/tablas.md`). Clic en una fila lleva a editar a ese profesional. Arriba, el botón **Nuevo profesional** y, al lado del título, cuántos hay en la tabla.
- **A la derecha, el panel de disciplinas**: cada una con un circulito de su color y la cantidad de profesionales que la tienen (quien tiene dos disciplinas cuenta en las dos). **Clic en una lleva a su pantalla**, donde se ve y se edita. Arriba, el botón **Nueva disciplina**.

## Los formularios

Crear y editar se hace en una pantalla aparte, igual que con los pacientes. **Guardar** vuelve al listado; **Cancelar** también, sin guardar. La forma general (secciones a la izquierda, panel fijo a la derecha) está explicada en `docs/estilos.md`, sección Formularios.

| URL | Qué es |
|---|---|
| `/administracion/profesionales/crear` | Formulario vacío para un profesional nuevo |
| `/administracion/profesionales/7/editar` | El mismo formulario, con los datos del usuario 7 |
| `/administracion/disciplinas/crear` | Formulario vacío para una disciplina nueva |
| `/administracion/disciplinas/3/editar` | El mismo formulario, con la disciplina 3 |

Cada formulario es un solo componente para crear y editar: si la URL trae un registro, la propiedad `usuarioId` (o `disciplinaId`) queda con su id y se está editando; si no, se está creando.

### Disciplinas

- **Nombre**: obligatorio y no se puede repetir.
- **Color**: con el que se pintan las etiquetas de esa disciplina en toda la app. Se elige con el selector de colores o escribiendo el código en **hexa** (numeral y seis caracteres, por ejemplo `#7c5cf0`); los dos campos manejan el mismo valor y al lado se ve cómo queda la etiqueta. Toda disciplina nace con el violeta de la app (`Disciplina::COLOR_INICIAL`). Conviene elegir colores no muy claros: la etiqueta usa ese color para la letra.
- Al editar, el panel de la derecha muestra **qué profesionales la tienen**. Es solo para mirar: se cambia desde el formulario de cada profesional.

Dónde se ve el color: en las etiquetas de disciplina de la tabla de profesionales y de los formularios, en el circulito del panel de disciplinas, y en el equipo de cada paciente (listado de pacientes y formulario de paciente). Cómo está hecho: `docs/estilos.md`, sección Color de las disciplinas.

### Profesionales (usuarios)

- **Nombre** y **email** son obligatorios. El email no se puede repetir.
- **Rol**: Dirección o Profesional, como dos opciones para elegir, cada una con una línea que explica qué puede hacer. Un usuario nuevo viene como Profesional. No se puede pasar a Profesional a la única persona de Dirección (ver `docs/roles.md`).
- **Disciplinas**: en el panel de la derecha hay un renglón por cada disciplina cargada. Se pueden tildar **varias** (por ejemplo, alguien que es psicóloga y psicopedagoga), una sola o ninguna (por ejemplo, administración). Al editar, destildar una y guardar se la quita.
- **Contraseña**: no hay campo acá. Todo usuario nuevo se crea con la contraseña **`password`** (constante `CONTRASENA_INICIAL` en `app/Livewire/Usuarios/Formulario.php`). Editar un usuario desde acá no cambia su contraseña: cada uno cambia la suya desde **Mi perfil** (ver `docs/perfil.md`).

## Archivos

| Archivo | Para qué |
|---|---|
| `routes/web.php` | Las URLs, dentro del grupo `administracion` (exigen sesión) |
| `app/Livewire/Usuarios/Lista.php` | Lógica del listado: tabla de profesionales y panel de disciplinas |
| `app/Livewire/Usuarios/Formulario.php` | Formulario de profesional (crear y editar): validaciones y guardado |
| `app/Livewire/Disciplinas/Formulario.php` | Formulario de disciplina (crear y editar), con su color |
| `resources/views/livewire/usuarios/` | Las vistas `lista` y `formulario` |
| `resources/views/livewire/disciplinas/formulario.blade.php` | La vista del formulario de disciplina |
| `resources/views/components/disciplina/` | La etiqueta y el circulito de color de una disciplina |
| `database/migrations/2026_10_09_230000_add_color_a_disciplinas_table.php` | Agrega la columna `color` |
| `resources/views/layouts/app.blade.php` | Barra superior, con el desplegable de Administración |
| `tests/Feature/DisciplinasTest.php`, `tests/Feature/UsuariosTest.php` | Pruebas |
