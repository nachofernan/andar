# Cifrado de datos sensibles

Andar guarda datos de salud de menores. La ley argentina de datos personales (25.326) los considera **datos sensibles** y pide medidas de seguridad acordes. Por eso los datos más delicados se guardan **cifrados**: si alguien se lleva el archivo de la base o un backup, no los puede leer sin la clave.

## Qué está cifrado hoy

| Tabla | Campo | Cifrado |
|---|---|---|
| `pacientes` | `dni` | Sí |
| `pacientes` | `diagnostico` | Sí |
| `pacientes` | `nombre`, `apellido` y el resto | No |
| `mensajes` | `cuerpo` (el texto; en las entradas de la bitácora, la lista de cambios con lo anterior y lo nuevo) | Sí |
| `mensajes` | tipo, autor, paciente y fecha | No |
| Archivos adjuntos | contenido y nombre original | Sí |
| Archivos adjuntos | tipo, tamaño y fecha | No |
| `users` | `email`, `telefono` | No |

`nombre` y `apellido` no se cifran porque la lista de pacientes se ordena por apellido, y un campo cifrado no se puede ordenar (ver "Límites"). Esos datos quedan protegidos por las medidas del servidor (ver "Pendiente para cuando se publique").

`email` y `telefono` de `users` tampoco se cifran: son datos de contacto laboral del equipo (no datos clínicos del paciente), y el email además necesita ser único y buscable (para el login), algo que un campo cifrado no permite (ver "Límites").

## Cómo funciona

Es el cifrado que trae Laravel (AES-256). Se activa con una línea por campo en el modelo, dentro de `casts()`:

```php
// app/Models/Paciente.php
'dni' => 'encrypted',
'diagnostico' => 'encrypted',
```

Con eso, Laravel cifra el dato justo antes de guardarlo y lo descifra al leerlo. En el resto del código no cambia nada: `$paciente->dni` devuelve el DNI normal. En la base, en cambio, queda un texto largo e ilegible.

## La clave (IMPORTANTE)

La clave con la que se cifra es `APP_KEY`, que está en el archivo `.env`.

- **Si se pierde `APP_KEY`, los datos cifrados no se pueden recuperar.** No hay forma de "resetearla".
- Hay que guardar una copia de `APP_KEY` en un lugar seguro y **separado de los backups de la base**. Si la clave y el backup están juntos, quien se lleve uno se lleva el otro y el cifrado no sirve de nada.
- `.env` nunca se sube a git (ya está en `.gitignore`).
- **Nunca correr `php artisan key:generate` en una instalación que ya tiene datos**: genera una clave nueva y los datos viejos quedan ilegibles.
- Cada instalación tiene su propia clave. Una base copiada de otra máquina solo se lee con el `APP_KEY` de esa máquina.

Si algún día hay que cambiar la clave: se pone la nueva en `APP_KEY` y la vieja en `APP_PREVIOUS_KEYS` (también en `.env`). Laravel cifra lo nuevo con la nueva y sigue pudiendo leer lo viejo con la anterior.

## Límites de un campo cifrado

El mismo dato cifrado dos veces da dos textos distintos. Por eso, sobre un campo cifrado la base **no puede**:

- buscar (`where dni = ...`),
- ordenar,
- garantizar que no se repita (`unique`).

Cuando hace falta algo de eso, se traen los registros y se compara ya descifrado, desde PHP. Es lo que hace el formulario de pacientes para controlar que el DNI no se repita (`app/Livewire/Pacientes/Formulario.php`). Con la cantidad de pacientes del centro (decenas, a lo sumo cientos) es instantáneo.

Ojo: ese control está **solo en el formulario**. Si alguna vez se cargan pacientes por otra vía (una importación, por ejemplo), hay que repetirlo ahí.

Además, la columna de un campo cifrado tiene que ser de tipo `text`, porque el texto cifrado es mucho más largo que el dato original.

## Regla para lo que viene

**Todo dato sensible nuevo nace cifrado.**

### Textos (como los mensajes)

Igual que `diagnostico`: el campo con el texto lleva `'encrypted'` en `casts()` del modelo y la columna es `text` en la migración. Una línea por campo. Así están hechos los mensajes (`app/Models/Mensaje.php`), que incluyen a los informes como un tipo de mensaje.

No se puede buscar dentro del texto desde la base. Si se necesita un buscador, se resuelve como el DNI (trayendo y comparando) o se define en ese momento.

### Archivos (como los adjuntos de los mensajes)

Los archivos se manejan con **Spatie Media Library**, que **no cifra por sí solo**. Por eso se hace así (el código está en `Mensaje::adjuntar()` y en `AdjuntoController`):

1. **Disco privado.** En `config/media-library.php` el disco por defecto es `local` (`storage/app/private`), una carpeta a la que no se llega desde el navegador. El valor original del paquete era `public`, donde cualquiera con el link abre el archivo sin iniciar sesión.
2. **Contenido cifrado.** El archivo se cifra (con la misma `APP_KEY`) antes de guardarse en el disco y se descifra recién al descargarlo.
3. **Descarga con sesión y permiso.** No hay link directo al archivo: se baja por `/adjuntos/{id}`, que exige sesión y ser del equipo del paciente.
4. **Nombre oculto.** En el disco el archivo queda con un nombre al azar terminado en `.enc`. El nombre original (que puede traer el nombre del paciente, como "Informe Tomás González.pdf") se guarda **cifrado** en la tabla `media`.

Qué queda a la vista de alguien que se lleve el disco o la base, y qué no:

| Dato | ¿Legible? |
|---|---|
| Contenido del archivo | No |
| Nombre original | No |
| Tipo (PDF, imagen…) y tamaño | Sí |
| A qué mensaje pertenece, y cuándo se subió | Sí |

Consecuencias a tener en cuenta:

- De un archivo cifrado no se pueden generar miniaturas ni vistas previas: solo se descarga.
- El archivo cifrado ocupa en disco alrededor de un tercio más que el original.
- Cada archivo se cifra y descifra entero en memoria. Con el límite actual de 10 MB no hay problema; para archivos mucho más grandes habría que cambiar el método.
- Al subir, el navegador manda el archivo a una carpeta temporal (`storage/app/private/livewire-tmp`), donde está sin cifrar unos instantes, junto a un archivo `.json` con su nombre original. Los dos se borran apenas se envía el mensaje (`borrarCopiaTemporal()` en `app/Livewire/Pacientes/Mensajes.php`). **Excepción**: si alguien elige un archivo y no llega a enviar el mensaje (cierra la pantalla, o el envío da error y no lo reintenta), esa copia sin cifrar queda en la carpeta temporal. Livewire limpia las que tienen más de 24 horas, pero recién cuando alguien sube otro archivo.
- **Backups**: hay que respaldar la base **y** la carpeta `storage/app/private`, y por separado `APP_KEY`. Sin la clave, los archivos tampoco se recuperan.

## Pendiente para cuando se publique

El cifrado por campo es una capa. Para cumplir de verdad faltan, del lado del servidor:

- Disco del servidor cifrado (protege todo lo que no está cifrado por campo).
- Backups cifrados, con `APP_KEY` guardada aparte.
- HTTPS obligatorio.

Y del lado de la app, ya anotado como pendiente en `docs/roles.md`: sacar el login precargado.

## Archivos

| Archivo | Para qué |
|---|---|
| `app/Models/Paciente.php` | Los `casts()` con `encrypted` |
| `app/Models/Mensaje.php` | `cuerpo` con `encrypted`; `adjuntar()` cifra y guarda un archivo |
| `app/Http/Controllers/AdjuntoController.php` | Descifra y entrega un adjunto, solo al equipo del paciente |
| `database/migrations/2026_10_09_000003_create_pacientes_table.php` | `dni` y `diagnostico` como `text` |
| `app/Livewire/Pacientes/Formulario.php` | Control de DNI repetido comparando ya descifrado |
| `config/media-library.php` | Disco privado por defecto para los archivos |
| `database/migrations/2026_10_09_111213_create_media_table.php` | Tabla `media` de Media Library |
| `tests/Feature/ModelosTest.php` | Prueba de que en la base queda cifrado |
| `tests/Feature/AdjuntosTest.php` | Prueba de que los archivos quedan cifrados y solo los baja el equipo |
