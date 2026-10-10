# Tablas con buscador, filtros y orden

Las tres tablas principales se pueden **buscar, filtrar y ordenar** sin recargar la página:

| Pantalla | Busca por | Filtros | Se ordena por |
|---|---|---|---|
| Inicio › Mis pacientes | Nombre y apellido | Estado (Activos / Inactivos / Todos) y "Solo con mensajes sin leer" | Paciente, Edad, Última actividad |
| Administración › Pacientes | Nombre, apellido y DNI (con o sin puntos) | Profesional y Estado (abre en Activos) | Paciente, Ingreso, Estado |
| Administración › Profesionales | Nombre y email | Disciplina | Nombre, Disciplinas (alfabético; con varias manda la primera, y quien no tiene ninguna va al final), Pacientes (cuántos atiende) |

## Cómo se usa

- **Buscador**: se escribe y la tabla se achica sola. No importan las mayúsculas ni las tildes: `gomez` encuentra a `Gómez`.
- **Filtros**: desplegables y casillas arriba de la tabla. Se combinan entre sí y con el buscador.
- **Orden**: clic en el título de una columna ordena por ella (aparece ▲). Otro clic en la misma invierte el sentido (▼). Las columnas sin flecha no se pueden ordenar. Cuando dos filas empatan en la columna elegida, entre ellas quedan por nombre.

En el Inicio hay una regla extra: **los pacientes con mensajes sin leer van siempre arriba**, se ordene por lo que se ordene. El orden elegido se aplica dentro de cada grupo (primero los que tienen sin leer, después el resto).

**La tabla se acuerda de cómo la dejaste.** El buscador, los filtros y el orden se mantienen mientras dure la sesión: si filtrás, entrás a un registro y volvés, sigue filtrada. Se reinician al salir de la app (Salir). Al lado del título de cada tabla hay una etiqueta con el número de filas que se ven en ese momento.

## Cómo está hecho

Todo lo común está en el trait `app/Livewire/Concerns/ConTabla.php`, que se agrega a un componente con `use ConTabla;`. Aporta:

- Las propiedades `buscar` (lo escrito en el buscador), `orden` (la columna elegida) y `ascendente` (el sentido).
- `ordenarPor($columna)`: lo que se ejecuta al hacer clic en un título.
- `coincide(...textos)`: responde si alguno de esos textos contiene lo buscado.
- `ordenar($filas)`: devuelve las filas ordenadas según `orden` y `ascendente`.

Cada pantalla define el método `columnasOrdenables()`: la lista de columnas por las que se puede ordenar y, para cada una, por qué valor. La **primera** de la lista es el orden con el que abre la pantalla. Si alguien manda desde el navegador una columna que no está en esa lista, se ignora.

Los filtros propios de cada pantalla (estado, profesional, disciplina, etc.) son propiedades comunes del componente, y se aplican en su `render()`.

Lo de "acordarse" lo hace el atributo `#[Session]` de Livewire, puesto arriba de cada propiedad (las tres del trait y los filtros de cada pantalla): Livewire guarda el valor en la sesión del usuario y lo vuelve a poner al abrir la pantalla. Un filtro nuevo necesita su `#[Session]` para recordarse.

En la vista:

- El buscador y los filtros llevan `wire:model.live`: al cambiar, Livewire vuelve a armar la tabla. El buscador espera 300 ms sin teclas (`debounce`) para no trabajar en cada letra.
- El título clickeable es el componente `<x-tabla.columna>` (`resources/views/components/tabla/columna.blade.php`).

### Por qué se filtra en PHP y no en la base

La pantalla trae la lista completa de la base y la filtra y ordena en PHP. Motivos:

- El **DNI está cifrado** (ver `docs/cifrado.md`): la base no puede buscar adentro.
- Así la búsqueda ignora tildes, cosa que SQLite no hace sola.

Con la cantidad de pacientes y profesionales de un centro es instantáneo. No hay paginación.

## Cómo sumar una columna ordenable

1. En el componente, agregarla a `columnasOrdenables()`: `'obra_social' => fn (Paciente $paciente) => $this->normalizar($paciente->obra_social ?? '')`. (`normalizar` saca tildes y mayúsculas para que el orden alfabético salga bien.)
2. En la vista, cambiar el `<th>` común por `<x-tabla.columna columna="obra_social" :$orden :$ascendente>Obra social</x-tabla.columna>`.

## Archivos

| Archivo | Para qué |
|---|---|
| `app/Livewire/Concerns/ConTabla.php` | Buscador y orden, común a las tres tablas |
| `resources/views/components/tabla/columna.blade.php` | Título de columna clickeable, con la flechita |
| `app/Livewire/Inicio.php`, `Pacientes/Lista.php`, `Usuarios/Lista.php` | Las tres pantallas que lo usan: columnas y filtros de cada una |
| `tests/Feature/` | Las pruebas de cada tabla están en el test de su pantalla |
