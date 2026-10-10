# Andar

App web (tipo SaaS) para el centro **Andar**. Cliente: **Catalina Arnal**, psicóloga infantil y una de las cabezas del centro.

Sirve para llevar el registro de pacientes (niños/as) y la comunicación del equipo de profesionales: mensajes, informes, archivos, etc. Los detalles se van definiendo **paso a paso** con el dueño.

Referencia visual (maqueta no funcional, solo para inspirarse): `C:\xampp\htdocs\consul2`.

## Stack

Instalado hoy:
- Laravel 13
- Livewire 4, **con componentes de clase** (NO Volt)
- SQLite
- Tailwind CSS 4 con Vite: los estilos son clases de Tailwind en las vistas, **sin CSS propio a mano** (ver `docs/estilos.md`)
- Spatie Permission: con él están hechos los dos roles, Dirección y Profesional (solo roles, sin permisos sueltos; ver `docs/roles.md`)
- Spatie Media Library (documentos e imágenes): instalado, con la tabla `media` y el disco privado por defecto. Lo usan los adjuntos de los mensajes, que se guardan cifrados

## Reglas de trabajo (OBLIGATORIAS)

1. **Hacer SOLO lo que se pide.** Nada de adelantarse, nada de scaffolding, modelos, pantallas ni paquetes que no se pidieron en ese paso. Ante la duda, preguntar.
2. **Simple**: sin sobreingeniería ni funciones no esenciales.
3. **Español siempre**: respuestas, comentarios, docs y nombres de dominio.
4. **El dueño debe entender TODO sin IA.** Por eso, cuando se escriba código:
   - Docblocks/comentarios en español explicando el *para qué* (clases, métodos, migraciones, vistas), y comentarios en líneas sueltas si el motivo no es obvio.
   - La documentación va en la carpeta `docs/` (se crea cuando haya algo que documentar) y se actualiza en el mismo trabajo que el cambio.
5. Trabajo paso a paso: un paso, se revisa, recién ahí el siguiente.
6. **Todo dato sensible nuevo nace cifrado** (textos de mensajes e informes, archivos de pacientes). Cómo: `docs/cifrado.md`.

## Comandos

```
php artisan serve        # servidor local (o XAMPP: http://localhost/andar/public)
php artisan test         # tests
php artisan migrate      # aplica migraciones
npm run build            # compila el CSS/JS (necesario para verlo por XAMPP)
npm run dev              # recompila solo mientras se programa
```

## Agentes (`.claude/agents/`)

- **tester**: corre y escribe tests. Usarlo después de cada cambio de código.
- **lector**: explica el código en español simple para que el dueño lo entienda. Solo lee, no modifica.

## Estado actual

Hecho:
- Login/logout (ver `docs/login.md`). La página de inicio saluda y muestra "Mis pacientes" (ver abajo).
- Vistas en Tailwind, con barra superior (ver `docs/estilos.md`): menú con Mis pacientes (el Inicio) y, para Dirección, **Administración** (todo bajo `/administracion`), que es un desplegable con Pacientes y Profesionales y disciplinas. El nombre del usuario es otro desplegable, con Mi perfil y Salir.
- Tablas con buscador, filtros y orden en el Inicio y en los dos listados de administración; recuerdan cómo se las dejó durante la sesión (trait `ConTabla`; ver `docs/tablas.md`).
- Formularios de paciente, profesional y disciplina, y Mi perfil, con secciones a la izquierda y panel fijo a la derecha (piezas `x-formulario.*`; ver `docs/estilos.md`).
- Base de datos y modelos de profesionales (`User`), `Disciplina` y `Paciente` (ver `docs/modelos.md`). Un profesional puede tener varias disciplinas.
- Profesionales (usuarios) y disciplinas se administran desde una sola pantalla de listado, con los formularios de crear y editar en página aparte; no se borran (ver `docs/disciplinas-y-usuarios.md`). Cada disciplina tiene un color en hexa, que pinta sus etiquetas (va en `style=""`: única excepción a "sin CSS a mano"). Solo las usa Dirección. Al crear un usuario la contraseña viene precargada con `password` (se puede cambiar ahí y al editarlo): es provisorio.
- Roles y permisos (ver `docs/roles.md`): Dirección carga y edita pacientes, equipos, usuarios y disciplinas, y ve todas las fichas; un Profesional ve solo los pacientes que atiende. El historial de mensajes y los archivos son solo del equipo del paciente, también para Dirección.
- El Inicio saluda con el aviso de mensajes sin leer y muestra la tabla "Mis pacientes": los que atiende el usuario logueado (Dirección o profesional), activos por defecto, con la última actividad de cada uno y los que tienen mensajes sin leer siempre arriba. El listado administrativo (`/administracion/pacientes`) es exclusivo de Dirección, y desde ahí cada fila lleva directo a editar, no a la ficha (ver `docs/pacientes.md`).
- "Mi perfil" (`/mi-perfil`, se entra desde el nombre del usuario): cualquier usuario cambia su nombre, correo, teléfono/WhatsApp (sin cifrar) y su contraseña (ver `docs/perfil.md`).
- Pantallas de pacientes: ficha, crear y editar, con asignación del equipo de profesionales; no se borran, se dan de baja con "Activo" (ver `docs/pacientes.md`). La ficha tiene la cabecera del paciente, el historial como lo principal y, al costado, diagnóstico y archivos. El diagnóstico lo cambia cualquiera del equipo desde la ficha.
- Historial de mensajes del equipo en la ficha del paciente, con forma de chat (lo nuevo abajo, se escribe al pie) y tipos (mensaje, informe, indicación, nota). Solo lo ve y escribe el equipo del paciente; no se editan ni se borran (ver `docs/mensajes.md`).
- El historial es también la **bitácora**: todo cambio en los datos de un paciente ya cargado pasa por `Paciente::actualizar()`, que deja sola una entrada de tipo `actualizacion` con lo anterior y lo nuevo (cifrada). Cualquier pantalla nueva que modifique pacientes tiene que usar ese método.
- Archivos adjuntos a los mensajes (PDF, Word, Excel, imágenes; 5 por mensaje, 10 MB), cifrados y con descarga solo para el equipo.
- Avisos de mensajes sin leer dentro de la app: un puntito rojo en el menú y, en "Mis pacientes" del inicio, la cantidad por paciente. Se marcan leídos al abrir la ficha.
- Zona horaria de la app: Argentina (`config/app.php`).
- Cifrado de datos sensibles: `dni` y `diagnostico` del paciente, el texto de los mensajes y los archivos adjuntos se guardan cifrados con `APP_KEY` (ver `docs/cifrado.md`). **Si se pierde `APP_KEY` no se recuperan.**
- Provisorio: el login viene precargado con el usuario de ejemplo (`app/Livewire/Login.php`); sacarlo antes de usar datos reales.

Datos de ejemplo (`php artisan migrate:fresh --seed`): 6 disciplinas con los colores del logo, 21 usuarios (todos con `password`; `catalina@andar.test` es de Dirección y los otros 20 profesionales, p. ej. `paula@andar.test`), 60 pacientes inventados con su equipo y 164 mensajes. Están escritos a mano en `database/seeders/`, salvo la mayoría de los mensajes, que se arman rotando un banco de frases, sin azar (ver `docs/modelos.md`).

## Pendiente (a definir con el dueño, no construir hasta que se pida)

Diseño y usabilidad: ya se rediseñaron para desktop todas las pantallas (index, formularios, Mi perfil y la ficha del paciente). Sigue mobile. Sacar el login precargado, antes de usar datos reales.

## Otros centros (decisión tomada)

La idea es ofrecer la app a otros centros. Se decidió hacerlo con **una instalación independiente por centro** (cada una con su base, su `APP_KEY` y sus archivos), **no** como un sistema compartido entre centros. Condición: un solo código para todos; lo que cambie entre centros (nombre, logo, tipos de mensaje) irá en configuración. Eso todavía no se hizo: se hace cuando aparezca el segundo centro.
