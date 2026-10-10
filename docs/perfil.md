# Mi perfil

Pantalla donde cada usuario cambia sus propios datos. Se llega haciendo clic en **el nombre del usuario**, arriba a la derecha: se abre un menú chico con **Mi perfil** (`/mi-perfil`) y **Salir**; la puede abrir cualquiera con sesión iniciada, sea Dirección o profesional.

## Qué se puede cambiar

- **Nombre** y **correo** (obligatorios; el correo no se puede repetir con el de otro usuario).
- **Teléfono / WhatsApp** (opcional): un solo campo de contacto, en texto plano (no es un dato clínico del paciente, ver `docs/cifrado.md`).
- **Contraseña**: es opcional. Si se dejan los tres campos en blanco, no se toca. Para cambiarla hay que escribir la contraseña actual (se comprueba contra la guardada) y la nueva dos veces, con al menos 8 caracteres.

El guardado es **todo o nada**: si se pidió cambiar la contraseña y la actual está mal, no se guarda ningún campo (ni siquiera el nombre o el correo), para no dejar el formulario en un estado a medio camino.

A la derecha, la tarjeta **En el centro** muestra, solo para mirar, el rol y las disciplinas del usuario: eso lo carga Dirección desde Administración.

## Quién puede entrar

Cualquier usuario con sesión iniciada, de cualquier rol. A diferencia de las pantallas de administración, esta no lleva `use SoloDireccion;`: cada uno edita solo su propio usuario (`auth()->user()`), nunca el de otra persona.

## Archivos

| Archivo | Para qué |
|---|---|
| `routes/web.php` | La URL `/mi-perfil` (exige sesión) |
| `app/Livewire/Perfil.php` | Lógica: carga los datos actuales, valida y guarda |
| `resources/views/layouts/app.blade.php` | El desplegable del nombre del usuario, con el link a esta pantalla |
| `resources/views/livewire/perfil.blade.php` | El formulario |
| `database/migrations/2026_10_09_203516_add_telefono_a_users_table.php` | Agrega la columna `telefono` a `users` |
| `app/Models/User.php` | `telefono` sumado a los campos que se pueden asignar |
| `tests/Feature/PerfilTest.php` | Pruebas |
