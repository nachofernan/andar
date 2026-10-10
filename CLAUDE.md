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
- Vistas en Tailwind, con barra superior de menú (ver `docs/estilos.md`).
- Base de datos y modelos de profesionales (`User`), `Disciplina` y `Paciente` (ver `docs/modelos.md`). Un profesional puede tener varias disciplinas.
- Pantallas para listar, crear y editar disciplinas y usuarios, sin borrar (ver `docs/disciplinas-y-usuarios.md`). Solo las usa Dirección. Los usuarios nuevos nacen con contraseña `password`: es provisorio.
- Roles y permisos (ver `docs/roles.md`): Dirección carga y edita pacientes, equipos, usuarios y disciplinas, y ve todas las fichas; un Profesional ve solo los pacientes que atiende. El historial de mensajes y los archivos son solo del equipo del paciente, también para Dirección.
- El Inicio muestra "Mis pacientes": los que atiende el usuario logueado (Dirección o profesional), activos por defecto con un toggle para sumar los inactivos. El listado administrativo (`/pacientes`) pasó a ser exclusivo de Dirección, y desde ahí cada fila lleva directo a editar, no a la ficha (ver `docs/pacientes.md`).
- "Mi perfil" (`/mi-perfil`): cualquier usuario cambia su nombre, correo, teléfono/WhatsApp (sin cifrar) y su contraseña (ver `docs/perfil.md`).
- Pantallas de pacientes: ficha, crear y editar, con asignación del equipo de profesionales; no se borran, se dan de baja con "Activo" (ver `docs/pacientes.md`).
- Historial de mensajes del equipo en la ficha del paciente, con tipos (mensaje, informe, indicación, nota). Solo lo ve y escribe el equipo del paciente; no se editan ni se borran (ver `docs/mensajes.md`).
- Archivos adjuntos a los mensajes (PDF, Word, Excel, imágenes; 5 por mensaje, 10 MB), cifrados y con descarga solo para el equipo.
- Avisos de mensajes sin leer dentro de la app: en el menú, en el listado administrativo y en "Mis pacientes" del inicio. Se marcan leídos al abrir la ficha.
- Zona horaria de la app: Argentina (`config/app.php`).
- Cifrado de datos sensibles: `dni` y `diagnostico` del paciente, el texto de los mensajes y los archivos adjuntos se guardan cifrados con `APP_KEY` (ver `docs/cifrado.md`). **Si se pierde `APP_KEY` no se recuperan.**
- Provisorio: el login viene precargado con el usuario de ejemplo (`app/Livewire/Login.php`); sacarlo antes de usar datos reales.

Datos de ejemplo (`php artisan migrate:fresh --seed`): 6 disciplinas, 8 usuarios (todos con `password`; `catalina@andar.test` es de Dirección y el resto profesionales, p. ej. `paula@andar.test`), 15 pacientes inventados con su equipo y 10 mensajes. Están escritos a mano en `database/seeders/` (ver `docs/modelos.md`).

## Pendiente (a definir con el dueño, no construir hasta que se pida)

Diseño y usabilidad de lo hecho hasta acá: es la etapa siguiente. Sacar el login precargado, antes de usar datos reales.

## Otros centros (decisión tomada)

La idea es ofrecer la app a otros centros. Se decidió hacerlo con **una instalación independiente por centro** (cada una con su base, su `APP_KEY` y sus archivos), **no** como un sistema compartido entre centros. Condición: un solo código para todos; lo que cambie entre centros (nombre, logo, tipos de mensaje) irá en configuración. Eso todavía no se hizo: se hace cuando aparezca el segundo centro.
