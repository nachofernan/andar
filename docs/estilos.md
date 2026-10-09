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

- **Con sesión**: una barra superior (logo, menú, nombre del usuario y botón Salir) y debajo el contenido, en una columna centrada.
- **Sin sesión** (login): nada más; la pantalla de login se centra sola.

Para sumar un link al menú, agregarlo a la lista del `@foreach` de la barra (nombre de la ruta => texto).

- Las pantallas Livewire lo usan solas.
- Las vistas comunes lo usan envolviendo su contenido en `<x-layouts::app> ... </x-layouts::app>` (ver `resources/views/inicio.blade.php`).

## Tests

Los tests no cargan el CSS real (`$this->withoutVite()` en `tests/TestCase.php`), así que pasan aunque no se haya corrido `npm run build`.
