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
- Spatie Permission (roles/permisos): instalado y el modelo `User` ya tiene `HasRoles`, pero **todavía no hay roles ni permisos creados**

Decidido pero **todavía NO instalado** (se agrega cuando toque):
- Spatie Media Library (documentos e imágenes)

## Reglas de trabajo (OBLIGATORIAS)

1. **Hacer SOLO lo que se pide.** Nada de adelantarse, nada de scaffolding, modelos, pantallas ni paquetes que no se pidieron en ese paso. Ante la duda, preguntar.
2. **Simple**: sin sobreingeniería ni funciones no esenciales.
3. **Español siempre**: respuestas, comentarios, docs y nombres de dominio.
4. **El dueño debe entender TODO sin IA.** Por eso, cuando se escriba código:
   - Docblocks/comentarios en español explicando el *para qué* (clases, métodos, migraciones, vistas), y comentarios en líneas sueltas si el motivo no es obvio.
   - La documentación va en la carpeta `docs/` (se crea cuando haya algo que documentar) y se actualiza en el mismo trabajo que el cambio.
5. Trabajo paso a paso: un paso, se revisa, recién ahí el siguiente.

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
- Login/logout (ver `docs/login.md`). Después de iniciar sesión hay una página provisoria que dice "Hola, nombre".
- Vistas en Tailwind, con barra superior de menú (ver `docs/estilos.md`).
- Base de datos y modelos de profesionales (`User`), `Disciplina` y `Paciente` (ver `docs/modelos.md`).
- Pantallas para listar, crear y editar disciplinas y usuarios, sin borrar (ver `docs/disciplinas-y-usuarios.md`). Los usuarios nuevos nacen con contraseña `password` y cualquier usuario con sesión puede entrar: ambas cosas son provisorias.
- Pantallas de pacientes: lista, ficha, crear y editar, con asignación del equipo de profesionales; no se borran, se dan de baja con "Activo" (ver `docs/pacientes.md`).
- Provisorio: el login viene precargado con el usuario de ejemplo (`app/Livewire/Login.php`); sacarlo antes de usar datos reales.

Datos de ejemplo (`php artisan migrate:fresh --seed`): tres disciplinas y la usuaria `catalina@andar.test` / `password`.

## Pendiente (a definir con el dueño, no construir hasta que se pida)

Roles y permisos concretos, cambio/renovación de contraseña, mensajes e informes, archivos.
