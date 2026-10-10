<?php

namespace Tests\Feature;

use App\Models\Disciplina;
use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas de los datos de ejemplo (`php artisan migrate:fresh --seed`).
 * Sirven de alarma: si un cambio en las tablas rompe los seeders, estos tests fallan.
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    public function test_los_seeders_corren_y_cargan_las_cantidades_esperadas(): void
    {
        $this->seed(); // Corre DatabaseSeeder, que llama a todos los seeders en orden.

        $this->assertSame(6, Disciplina::count());
        $this->assertSame(21, User::count());
        $this->assertSame(60, Paciente::count());
        // Los mensajes se arman solos en su mayoría (ver MensajeSeeder::generarMensajes), por eso no se
        // comprueba un número exacto sino que haya volumen.
        $this->assertGreaterThanOrEqual(100, Mensaje::count());
    }

    public function test_cada_disciplina_de_ejemplo_tiene_un_color_distinto(): void
    {
        $this->seed();

        // Seis disciplinas, seis colores: si dos compartieran color sus etiquetas no se distinguirían.
        $this->assertCount(6, Disciplina::pluck('color')->unique());
    }

    public function test_los_pacientes_de_ejemplo_no_repiten_dni(): void
    {
        $this->seed();

        // Los mensajes de ejemplo buscan al paciente por DNI: uno repetido los mandaría al paciente equivocado.
        $this->assertCount(60, Paciente::all()->pluck('dni')->unique());
    }

    public function test_cada_mensaje_de_ejemplo_lo_escribe_alguien_del_equipo_de_su_paciente(): void
    {
        $this->seed();

        // Solo el equipo puede escribir en el historial: los datos de ejemplo tienen que respetar esa regla.
        // De paso confirma que ningún mensaje quedó sin autor (un mail mal escrito en el seeder).
        foreach (Mensaje::with('paciente', 'autor')->get() as $mensaje) {
            $this->assertTrue(
                $mensaje->paciente->esDelEquipo($mensaje->autor),
                "El autor del mensaje {$mensaje->id} no es del equipo de su paciente.",
            );
        }
    }

    public function test_catalina_arranca_con_mensajes_sin_leer_en_tomas_y_en_benjamin(): void
    {
        $this->seed();

        $catalina = User::where('email', 'catalina@andar.test')->firstOrFail();
        // El DNI está cifrado, así que no se puede buscar con where: se traen todos y se compara desde PHP.
        $tomas = Paciente::all()->firstWhere('dni', '52118304');
        $benjamin = Paciente::all()->firstWhere('dni', '50877421');

        // Tomás: lo vio hasta el 5/10 al mediodía, le queda solo el mensaje posterior que escribió otra persona.
        // Benjamín: nunca lo abrió, le quedan los dos que no son suyos.
        // Tiene sin leer también en otros pacientes (mensajes generados), pero esos no se comprueban acá.
        $sinLeer = $catalina->mensajesSinLeer();

        $this->assertSame(1, $sinLeer[$tomas->id]);
        $this->assertSame(2, $sinLeer[$benjamin->id]);
    }

    public function test_catalina_existe_con_disciplina_psicologia_y_contrasena_password(): void
    {
        $this->seed();

        // El login viene precargado con este mail (app/Livewire/Login.php): no puede faltar.
        $catalina = User::where('email', 'catalina@andar.test')->first();

        $this->assertNotNull($catalina);
        $this->assertSame(['Psicología'], $catalina->disciplinas->pluck('nombre')->all());
        // La contraseña se guarda encriptada, por eso se compara con Hash::check y no con el texto.
        $this->assertTrue(Hash::check('password', $catalina->password));
    }

    public function test_catalina_es_de_direccion_y_el_resto_son_profesionales(): void
    {
        $this->seed();

        // Sin nadie de Dirección no se podrían cargar usuarios ni pacientes desde la app.
        $this->assertSame(['catalina@andar.test'], User::role('direccion')->pluck('email')->all());
        $this->assertSame(20, User::role('profesional')->count());

        // Cada usuario tiene exactamente un rol: nadie quedó sin rol ni con los dos.
        foreach (User::with('roles')->get() as $usuario) {
            $this->assertCount(1, $usuario->roles, "{$usuario->email} no tiene exactamente un rol.");
        }
    }

    public function test_un_usuario_del_equipo_puede_tener_dos_disciplinas(): void
    {
        $this->seed();

        $lucia = User::where('email', 'lucia@andar.test')->first();

        // Confirma que el seeder llenó la tabla intermedia disciplina_user con más de una por persona.
        $this->assertNotNull($lucia);
        $this->assertSame(['Psicología', 'Psicopedagogía'], $lucia->disciplinas->pluck('nombre')->all());
    }

    public function test_un_usuario_del_equipo_puede_no_tener_disciplina(): void
    {
        $this->seed();

        $romina = User::where('email', 'romina@andar.test')->first();

        $this->assertNotNull($romina);
        $this->assertCount(0, $romina->disciplinas);
    }

    public function test_hay_pacientes_con_equipo_sin_equipo_e_inactivos(): void
    {
        $this->seed();

        // Con equipo: confirma que se llenó la tabla intermedia paciente_user.
        $this->assertSame(57, Paciente::has('profesionales')->count());
        // Sin equipo asignado quedan tres: Lautaro Sánchez, Mora Leiva y Zoe Barrios.
        $this->assertSame(3, Paciente::doesntHave('profesionales')->count());
        $this->assertSame(6, Paciente::where('activo', false)->count());
    }

    public function test_el_dni_de_los_pacientes_de_ejemplo_se_lee_sin_cifrar(): void
    {
        $this->seed();

        // El DNI está cifrado en la base, así que no se puede buscar con where:
        // se traen todos los pacientes y se compara desde PHP, ya descifrado por el modelo.
        $paciente = Paciente::all()->firstWhere('dni', '5123456');

        $this->assertNotNull($paciente);
        $this->assertSame('Álvarez', $paciente->apellido);
    }
}
