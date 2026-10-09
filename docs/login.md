# Login

Inicio y cierre de sesión con email y contraseña. No hay registro público ni "olvidé mi contraseña".

## Cómo funciona

1. Quien entra a `/` sin sesión es redirigido a `/login` (middleware `auth`).
2. `/login` muestra el componente `App\Livewire\Login` (vista `resources/views/livewire/login.blade.php`).
3. Al enviar el formulario, Livewire llama al método `entrar()`, que valida los campos y usa `Auth::attempt()` para comparar con la base.
   - Si falla: error "Email o contraseña incorrectos".
   - Si sale bien: renueva la sesión y redirige a donde quería ir (o a `/`).
4. `/` (ruta `inicio`) es una página provisoria: "Hola, nombre".
5. El botón **Salir** está en la barra superior, en todas las pantallas. Hace un `POST /logout`: cierra y destruye la sesión y vuelve al login.
6. Si ya hay sesión y se entra a `/login`, el middleware `guest` lo manda al inicio.

## Archivos

| Archivo | Para qué |
|---|---|
| `routes/web.php` | Las URLs y cuáles exigen sesión |
| `app/Livewire/Login.php` | Lógica del login |
| `resources/views/livewire/login.blade.php` | Formulario |
| `resources/views/layouts/app.blade.php` | Marco HTML de todas las pantallas. Con sesión muestra la barra con el menú y el botón Salir |
| `resources/views/inicio.blade.php` | Página provisoria posterior al login |
| `resources/css/app.css` | Colores y fuente de la app. El resto del estilo son clases de Tailwind en cada vista (ver `docs/estilos.md`) |
| `tests/Feature/LoginTest.php` | Pruebas |

## Spatie Permission

Está instalado (migración y `config/permission.php` publicados, `User` usa `HasRoles`), pero todavía no se usa: no hay roles ni permisos creados. Se definen en el próximo paso que lo requiera.

## Login precargado (provisorio)

Para probar más rápido, el formulario arranca con el email y la contraseña del usuario de ejemplo ya escritos. Son los valores iniciales de `$email` y `$password` en `app/Livewire/Login.php`. **Hay que sacarlos antes de usar la app con datos reales.**

## Usuarios

Se crean a mano o por seeder. Ejemplo de desarrollo: `catalina@andar.test` / `password`.
