# Disciplinas y usuarios

Dos pantallas para cargar las disciplinas del centro y los usuarios (profesionales). En las dos se puede **listar, crear y editar**. No se puede borrar.

Se llega desde el menú de arriba: **Disciplinas** (`/disciplinas`) y **Usuarios** (`/usuarios`).

## Quién puede entrar

Por ahora, **cualquier usuario con sesión iniciada**. Todavía no hay roles; cuando se definan, se restringe.

## Cómo funcionan

Las dos pantallas tienen la misma forma: un formulario arriba y la lista abajo.

- Con el formulario vacío, **Guardar** crea uno nuevo.
- **Editar** (en una fila de la lista) carga esos datos en el formulario; **Guardar** los actualiza y **Cancelar** vuelve al formulario vacío.

Por dentro, cada componente tiene la propiedad `editandoId`: si está vacía se está creando, si tiene un id se está editando ese registro.

### Disciplinas

- Un solo campo: **nombre**. Obligatorio y no se puede repetir.
- La lista muestra cuántos profesionales tiene cada disciplina.

### Usuarios

- **Nombre** y **email** son obligatorios. El email no se puede repetir.
- **Disciplina** es opcional ("Sin disciplina").
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
