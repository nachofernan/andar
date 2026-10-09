# Disciplinas y usuarios

Dos pantallas para cargar las disciplinas del centro y los usuarios (profesionales). En las dos se puede **listar, crear y editar**. No se puede borrar.

Se llega desde el menú de arriba: **Disciplinas** (`/disciplinas`) y **Usuarios** (`/usuarios`).

## Quién puede entrar

**Solo Dirección.** A un profesional no le aparecen en el menú y, si entra por la dirección, ve un error 403 (ver `docs/roles.md`).

## Cómo funcionan

Las dos pantallas tienen la misma forma: un formulario arriba y la lista abajo.

- Con el formulario vacío, **Guardar** crea uno nuevo.
- **Editar** (en una fila de la lista) carga esos datos en el formulario; **Guardar** los actualiza y **Cancelar** vuelve al formulario vacío.

Por dentro, cada componente tiene la propiedad `editandoId`: si está vacía se está creando, si tiene un id se está editando ese registro.

### Disciplinas

- Un solo campo: **nombre**. Obligatorio y no se puede repetir.
- La lista muestra cuántos profesionales tiene cada disciplina. Quien tiene dos disciplinas cuenta en las dos.

### Usuarios

- **Nombre** y **email** son obligatorios. El email no se puede repetir.
- **Rol**: Dirección o Profesional. Un usuario nuevo viene como Profesional. No se puede pasar a Profesional a la única persona de Dirección (ver `docs/roles.md`).
- **Disciplinas**: hay una casilla por cada disciplina cargada. Se pueden tildar **varias** (por ejemplo, alguien que es psicóloga y psicopedagoga), una sola o ninguna (por ejemplo, administración). Al editar, destildar una y guardar se la quita.
- En la lista, las disciplinas de cada usuario aparecen separadas por coma.
- **Contraseña**: no hay campo. Todo usuario nuevo se crea con la contraseña **`password`** (constante `CONTRASENA_INICIAL` en `app/Livewire/Usuarios.php`). Editar un usuario no cambia su contraseña.

> Esto es provisorio: todavía no existe una forma de cambiar o renovar la contraseña. Hay que resolverlo antes de usar la app con datos reales.

## Archivos

| Archivo | Para qué |
|---|---|
| `routes/web.php` | Las URLs `/disciplinas` y `/usuarios` (exigen sesión) |
| `app/Livewire/Disciplinas.php` | Lógica de la pantalla de disciplinas |
| `resources/views/livewire/disciplinas.blade.php` | Formulario y lista de disciplinas |
| `app/Livewire/Usuarios.php` | Lógica de la pantalla de usuarios |
| `resources/views/livewire/usuarios.blade.php` | Formulario y lista de usuarios |
| `resources/views/layouts/app.blade.php` | Barra superior con el menú y el botón Salir |
| `tests/Feature/DisciplinasTest.php`, `tests/Feature/UsuariosTest.php` | Pruebas |
