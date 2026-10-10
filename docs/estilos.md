# Estilos (Tailwind)

La app usa **Tailwind CSS 4**. No hay archivos de CSS propios con clases a mano: el aspecto de cada cosa se define con clases de Tailwind escritas directamente en la vista (ej. `class="rounded-xl bg-brand px-4 py-3 text-white"`).

## Cómo funciona

1. `resources/css/app.css` importa Tailwind y define los colores y la fuente de la app.
2. **Vite** lee las vistas, ve qué clases se usan y arma un CSS final solo con esas, en `public/build/`.
3. El layout (`resources/views/layouts/app.blade.php`) lo carga con `@vite(...)`.

## Comandos

```
npm install      # una sola vez (o cuando cambia package.json): baja las dependencias
npm run build    # compila el CSS/JS a public/build
npm run dev      # mientras se programa: recompila solo al guardar
```

- Entrando por XAMPP (`http://localhost/andar/public`) hay que correr `npm run build` después de cambiar clases en una vista; si no, la clase nueva no tiene efecto.
- Con `npm run dev` prendido, los cambios se ven al instante.

## Colores

Están en el bloque `@theme` de `resources/css/app.css`. Cambiando el valor ahí se cambia en toda la app.

| Variable | Clases que genera (ejemplos) | Uso |
|---|---|---|
| `--color-brand` | `bg-brand`, `text-brand`, `border-brand` | Violeta principal |
| `--color-brand-dark` | `hover:bg-brand-dark` | Violeta al pasar el mouse |
| `--color-brand-soft` | `bg-brand-soft` | Violeta muy claro (botón secundario) |
| `--color-accent` | `to-accent` | Verde agua, detalles |
| `--color-ink` | `text-ink` | Texto normal |
| `--color-muted` | `text-muted` | Texto secundario |
| `--color-line` | `border-line` | Bordes |

Además se pueden usar todas las clases estándar de Tailwind (`text-red-600`, `gap-4`, etc.).

## Layout común

`resources/views/layouts/app.blade.php` es el marco HTML de todas las pantallas. Pone el fondo con degradé y:

- **Con sesión**: una barra superior y debajo el contenido, en una columna centrada. La barra tiene:
  - El logo y el **menú**: **Mis pacientes** (es el Inicio; lleva un puntito rojo si hay mensajes sin leer) y, solo para Dirección, **Administración**, que al hacer clic abre un menú chico con **Pacientes** y **Profesionales y disciplinas**.
  - A la derecha, **el nombre del usuario**: al hacer clic se abre un menú chico con **Mi perfil** y **Salir**.
- **Sin sesión** (login): nada más; la pantalla de login se centra sola.

Para sumar una pantalla al desplegable de Administración, agregarla a la lista `$administracion` de la barra (texto => ruta a la que lleva y rutas en las que queda marcada).

Los dos desplegables se abren y se cierran con **Alpine** (`x-data`, `@click`, `x-show`): es una librería chica de JavaScript que ya viene incluida con Livewire, no hay que instalar nada. Además de acá, se usa en los formularios para dos detalles que se actualizan al instante: el contador de elegidos del equipo y la vista previa del color de una disciplina.

- Las pantallas Livewire lo usan solas.
- Una vista común (que no sea de Livewire) lo usaría envolviendo su contenido en `<x-layouts::app> ... </x-layouts::app>`. Hoy no hay ninguna: todas las pantallas son de Livewire.

## Mobile

En el celular las pantallas se acomodan con clases que empiezan con `max-md:` o con `md:hidden`; el resto de las clases es desktop. La regla y qué cambia en cada pantalla: `docs/mobile.md`.

## Tablas

Las tablas con buscador, filtros y orden comparten sus piezas: ver `docs/tablas.md`.

## Listados

Las tarjetas de listado (Mis pacientes, Pacientes, Profesionales, Disciplinas) tienen dos alturas: arriba el **título con la cantidad** de filas y, a la derecha, la acción (**+ Nuevo**, un link sin fondo); abajo, el buscador y los filtros.

## Formularios

Los tres formularios de administración (paciente, profesional, disciplina) y **Mi perfil** tienen la misma forma: a la izquierda los datos, en **secciones**; a la derecha un **panel** que queda fijo al bajar con el scroll, con lo que se elige (equipo, disciplinas) y los botones **Guardar** y **Cancelar**. Arriba, solo un link para volver al listado: no llevan título ("Editar paciente", "Nuevo profesional"), porque las secciones ya dicen qué se está cargando.

Piezas comunes, en `resources/views/components/formulario/`:

- `<x-formulario.seccion titulo="..." ayuda="...">`: la tarjeta blanca de una sección, con su título.
- `<x-formulario.campo nombre="..." opcional error="...">`: un campo, con su nombre arriba, el "(opcional)" en la misma línea y el mensaje de error debajo. Adentro va el `<input>`.

Las clases de los controles están una sola vez por vista, en la variable `$control` del principio.

Los renglones para tildar (equipo, disciplinas, rol) se resaltan solos cuando están elegidos, con `has-[:checked]:` de Tailwind: "si adentro hay una casilla tildada".

## Color de las disciplinas

Cada disciplina tiene su color, que se carga en su formulario y se guarda en la base (ver `docs/disciplinas-y-usuarios.md`). Como el color viene de la base, **no puede ser una clase de Tailwind**: va escrito en `style="..."`. Es la única excepción a "sin CSS a mano", y está encerrada en dos piezas de `resources/views/components/disciplina/`:

- `<x-disciplina.etiqueta :disciplina="$d" />`: la etiqueta con el nombre, la letra del color y el fondo del mismo color muy clarito.
- `<x-disciplina.punto :disciplina="$d" />`: un circulito del color.

También lo usa `<x-avatar :usuario="$u" />` (`resources/views/components/avatar.blade.php`): el círculo con las iniciales de una persona en el historial, pintado con el color de su primera disciplina.

## Tests

Los tests no cargan el CSS real (`$this->withoutVite()` en `tests/TestCase.php`), así que pasan aunque no se haya corrido `npm run build`.
