# Versión mobile

La app se diseñó primero para la compu. La versión para celular está hecha **encima**, sin cambiar nada de lo que se ve en desktop.

## La regla

En las vistas, lo de mobile se reconoce por dos prefijos de Tailwind:

| Se escribe | Quiere decir | Ejemplo |
|---|---|---|
| `max-md:algo` | "aplicá `algo` solo en pantallas de menos de 768 px" | `max-md:hidden` = oculto en el celular |
| `md:hidden` | "oculto de 768 px para arriba" | un bloque que existe solo en el celular |

Todo lo demás (las clases sin esos prefijos) es desktop y **no se toca**: para la versión mobile solo se agregaron clases y bloques, no se sacó ni cambió ninguna clase existente. Por eso de 768 px para arriba la app se ve igual que antes.

768 px es el corte: los celulares quedan de un lado; las tablets y las compus, del otro.

Para ajustar algo de mobile: buscar `max-md:` o `md:hidden` en la vista. Para probarlo en la compu: en el navegador, F12 y el ícono del celular (modo responsive), con un ancho de unos 390 px. Después de cambiar clases hay que correr `npm run build` (ver `docs/estilos.md`).

## Qué cambia en cada pantalla

### En todas
- Las tarjetas tienen menos relleno y la página menos margen.
- Los campos para escribir usan letra de 16 px (`max-md:text-base`). No es por gusto: con letra más chica, el iPhone hace zoom solo al tocar un campo.
- Buscadores y filtros ocupan todo el ancho. En Mis pacientes, el filtro de estado y la casilla comparten renglón; por eso ahí la casilla dice solo "Solo sin leer".
- El alto mínimo de la página se mide con `dvh` (lo que se ve de verdad) y no con `screen`, que cuenta también lo que tapa la barra del navegador del celular y dejaba un scroll de más (se notaba en el login).

### Barra superior
Queda fija arriba al bajar con el scroll. Tiene el logo y, a la derecha, el botón **☰** (con el puntito rojo si hay mensajes sin leer). Al tocarlo se abre, debajo de la barra, una lista con todo: Mis pacientes, las pantallas de Administración (solo Dirección), Mi perfil y Salir. El menú horizontal y el desplegable del nombre no se ven.

El panel usa la misma lista `$administracion` que el desplegable de desktop: una pantalla nueva agregada ahí aparece en los dos.

### Tablas (Mis pacientes, Pacientes, Profesionales)
Se ven como **lista**: queda solo la primera columna y no se muestran los títulos. Lo que decían las otras columnas va en un renglón chico debajo del nombre:

| Tabla | Debajo del nombre | No se muestra |
|---|---|---|
| Mis pacientes | edad · fecha y autor del último mensaje | — |
| Pacientes (administración) | DNI · edad (igual que en desktop) | Ingreso y Equipo |
| Profesionales | correo y etiquetas de sus disciplinas | Cantidad de pacientes |

Los pacientes inactivos llevan la etiqueta "Inactivo" al lado del nombre.

Como no hay títulos, **en el celular no se puede cambiar el orden**: queda el último que se eligió en la compu (o el de siempre). Buscador y filtros funcionan igual.

Los listados no tienen forma de tarjeta: van de borde a borde de la pantalla, sin esquinas redondeadas ni sombra.

En "Profesionales y disciplinas" los dos bloques van uno debajo del otro. Arriba Disciplinas, plegada: se ve el título, la cantidad y "+ Nueva", y la tabla aparece al tocar **"Ver disciplinas ▼"** (variable `abierta` de Alpine, en `usuarios/lista.blade.php`). Debajo, Profesionales.

### Ficha del paciente
En el celular la ficha **es el chat**.

- La cabecera muestra el nombre, el estado y la línea de DNI, edad, grado y escuela.
- El botón **"Ver datos, equipo y diagnóstico ▼"** muestra lo demás: nacimiento, ingreso y obra social, y los bloques de Equipo, Diagnóstico y Archivos. Arranca cerrado. Al abrirlo no son tarjetas aparte: se ven como la continuación de la tarjeta de la cabecera, separados por una línea.
- Los filtros del historial (fechas y tipo) están detrás de **"Filtrar historial ▼"**. Si hay un filtro puesto, "Borrar filtros" queda siempre a la vista.
- El historial no es una tarjeta: va de borde a borde de la pantalla y ocupa su alto. Los globos son más anchos y no muestran las disciplinas de quien escribe.
- El cuadro para escribir es más bajo, y debajo van en un solo renglón el tipo de mensaje, el clip para adjuntar (sin la palabra "Adjuntar") y Enviar.

Abrir y cerrar esos dos bloques lo maneja Alpine (ver `docs/estilos.md`), con las variables `datos` (en `ficha.blade.php`) y `filtros` (en `mensajes.blade.php`). El bloque lleva `max-md:hidden` y Alpine le saca esa clase cuando la variable es verdadera. En desktop esas variables no cambian nada, porque la clase solo vale en mobile.

### Formularios y Mi perfil
Una sola columna: primero las secciones, después el panel (equipo, disciplinas o profesionales) y al final Guardar y Cancelar. Los campos que en desktop van de a dos por renglón van de a uno. El panel ya no queda fijo al hacer scroll. No se oculta nada.

### Login y ventana del diagnóstico
Solo menos relleno y la letra de 16 px en los campos. El cuadro del diagnóstico es más bajo, para que entre con el teclado abierto.

## Archivos

Todo está en `resources/views/`: el layout, las vistas de `livewire/` y las piezas `components/modal` y `components/formulario/seccion`. No hay PHP ni CSS propio para mobile.

Test: `tests/Feature/RolesTest.php` verifica que el menú ☰ muestra Administración solo a Dirección.
