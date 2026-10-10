<?php

namespace Tests\Feature;

use App\Livewire\Pacientes\Ficha;
use App\Livewire\Pacientes\Mensajes;
use App\Models\Mensaje;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Pruebas de los archivos adjuntos a los mensajes del historial.
 * Reglas principales: se guardan cifrados (contenido y nombre) y solo el equipo del paciente puede verlos y bajarlos.
 */
class AdjuntosTest extends TestCase
{
    use RefreshDatabase; // Cada test arranca con la base vacía.

    // Contenido conocido, para poder buscarlo después en el disco y en la descarga.
    private const CONTENIDO = 'Contenido confidencial del informe';

    protected function setUp(): void
    {
        parent::setUp();

        // Disco de mentira: los adjuntos de los tests no se mezclan con los archivos reales y se borran solos.
        Storage::fake('local');
    }

    /** Crea un paciente directo en la base (no hay factory de pacientes), con los datos obligatorios. */
    private function crearPaciente(): Paciente
    {
        return Paciente::create([
            'nombre' => 'Ana',
            'apellido' => 'Zapata',
            'dni' => '52118304',
            'fecha_nacimiento' => '2018-10-08',
        ])->refresh();
    }

    /** Crea un profesional y lo suma al equipo del paciente. */
    private function crearProfesionalDelEquipo(Paciente $paciente, string $nombre = 'Lucía Pérez'): User
    {
        $profesional = User::factory()->create(['name' => $nombre]);
        $paciente->profesionales()->attach($profesional->id);

        return $profesional;
    }

    /** Un PDF de mentira con contenido conocido. */
    private function pdf(string $nombre = 'informe-tomas.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, self::CONTENIDO);
    }

    /** Guarda un mensaje con un adjunto directo por el modelo, sin pasar por el formulario. Devuelve el adjunto. */
    private function crearAdjunto(Paciente $paciente, User $autor, string $nombre = 'informe-tomas.pdf'): Media
    {
        $mensaje = Mensaje::create([
            'paciente_id' => $paciente->id,
            'user_id' => $autor->id,
            'tipo' => 'informe',
            'cuerpo' => 'Adjunto el informe.',
        ]);
        $mensaje->adjuntar($this->pdf($nombre));

        return $mensaje->getFirstMedia('adjuntos');
    }

    // ---------------------------------------------------------------- Enviar

    public function test_se_puede_enviar_un_mensaje_con_un_archivo_adjunto(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Adjunto el informe.')
            ->set('archivos', [$this->pdf()])
            ->call('enviar')
            ->assertHasNoErrors()
            // El formulario vuelve a quedar sin archivos, y "envios" sube para que la vista vacíe el selector.
            ->assertSet('archivos', [])
            ->assertSet('envios', 1);

        $adjuntos = Mensaje::firstOrFail()->getMedia('adjuntos');

        $this->assertCount(1, $adjuntos);
        $this->assertSame('local', $adjuntos[0]->disk);
        // El tipo y el tamaño originales quedan anotados, porque el archivo guardado ya no los refleja.
        $this->assertSame('application/pdf', $adjuntos[0]->getCustomProperty('tipo'));
        $this->assertSame(strlen(self::CONTENIDO), $adjuntos[0]->getCustomProperty('tamano'));
    }

    public function test_se_puede_enviar_un_mensaje_con_varios_archivos(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Van el informe y la planilla.')
            ->set('archivos', [
                $this->pdf('informe.pdf'),
                UploadedFile::fake()->create('planilla.xlsx', 20),
                UploadedFile::fake()->create('dibujo.png', 20),
            ])
            ->call('enviar')
            ->assertHasNoErrors();

        // Los tres quedan en el mismo mensaje.
        $this->assertDatabaseCount('mensajes', 1);
        $this->assertCount(3, Mensaje::firstOrFail()->getMedia('adjuntos'));
    }

    // ---------------------------------------------------------------- Validación

    public function test_un_archivo_de_un_tipo_no_permitido_da_error(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Texto válido.')
            ->set('archivos', [UploadedFile::fake()->create('programa.exe', 20)])
            ->call('enviar')
            ->assertHasErrors(['archivos.0' => 'mimes'])
            ->assertSee('Solo se aceptan PDF, Word, Excel e imágenes (JPG o PNG).');

        // No se guarda ni el mensaje ni el archivo.
        $this->assertDatabaseCount('mensajes', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_un_archivo_de_mas_de_10_mb_da_error(): void
    {
        $paciente = $this->crearPaciente();

        // 10 MB justos son 10240 KB: se prueba con uno más.
        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Texto válido.')
            ->set('archivos', [UploadedFile::fake()->create('grande.pdf', Mensaje::MAX_KB_ADJUNTO + 1)])
            ->call('enviar')
            ->assertHasErrors(['archivos.0' => 'max'])
            ->assertSee('Cada archivo puede pesar hasta 10 MB.');

        $this->assertDatabaseCount('mensajes', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_mas_de_5_archivos_en_un_mensaje_da_error(): void
    {
        $paciente = $this->crearPaciente();

        $archivos = [];
        for ($i = 1; $i <= Mensaje::MAX_ADJUNTOS + 1; $i++) {
            $archivos[] = UploadedFile::fake()->create("archivo-{$i}.pdf", 20);
        }

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Texto válido.')
            ->set('archivos', $archivos)
            ->call('enviar')
            ->assertHasErrors(['archivos' => 'max'])
            ->assertSee('Se pueden adjuntar hasta 5 archivos por mensaje.');

        $this->assertDatabaseCount('mensajes', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_el_cuerpo_vacio_da_error_aunque_haya_un_archivo(): void
    {
        $paciente = $this->crearPaciente();

        // Un archivo solo, sin texto, no alcanza: el mensaje siempre lleva algo escrito.
        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('archivos', [$this->pdf()])
            ->call('enviar')
            ->assertHasErrors(['cuerpo' => 'required']);

        $this->assertDatabaseCount('mensajes', 0);
        $this->assertDatabaseCount('media', 0);
    }

    // ---------------------------------------------------------------- Cifrado

    public function test_el_archivo_se_guarda_en_el_disco_cifrado_y_con_un_nombre_al_azar(): void
    {
        $paciente = $this->crearPaciente();

        // Se envía por el formulario para probar el camino completo, igual que en el uso real.
        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Adjunto el informe.')
            ->set('archivos', [$this->pdf()])
            ->call('enviar')
            ->assertHasNoErrors();

        // Todo lo que quedó en el disco de los adjuntos: es lo que vería alguien que abre la carpeta.
        $archivos = Storage::disk('local')->allFiles();

        $this->assertCount(1, $archivos);
        $this->assertStringEndsWith('.enc', $archivos[0]);
        $this->assertStringNotContainsString('informe-tomas', $archivos[0]);
        $this->assertStringNotContainsString(self::CONTENIDO, Storage::disk('local')->get($archivos[0]));
    }

    public function test_despues_de_enviar_no_queda_ninguna_copia_temporal_sin_cifrar_del_archivo(): void
    {
        $paciente = $this->crearPaciente();

        // Disco y carpeta donde Livewire deja lo que se sube hasta que se envía el formulario.
        // En los tests es un disco de mentira ('tmp-for-tests'), carpeta 'livewire-tmp'.
        $temporal = FileUploadConfiguration::storage();
        $carpeta = FileUploadConfiguration::directory();

        $formulario = Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Adjunto el informe.')
            ->set('archivos', [$this->pdf()]);

        // Antes de enviar la copia temporal existe y está en claro. Sin esta comprobación el test
        // pasaría aunque se mirara un disco equivocado, y no probaría que el envío la borra.
        $antes = collect($temporal->allFiles($carpeta))->map(fn ($ruta) => $temporal->get($ruta));
        $this->assertTrue($antes->contains(self::CONTENIDO), 'La subida tendría que dejar una copia temporal en claro.');

        $formulario->call('enviar')->assertHasNoErrors();

        // Después de enviar no queda nada de la subida en la carpeta temporal.
        $this->assertSame([], $temporal->allFiles($carpeta));

        // Y en ningún archivo de ninguno de los dos discos aparece el contenido ni el nombre original.
        foreach ([$temporal, Storage::disk('local')] as $disco) {
            foreach ($disco->allFiles() as $ruta) {
                $this->assertStringNotContainsString(self::CONTENIDO, $disco->get($ruta));
                $this->assertStringNotContainsString('informe-tomas', $disco->get($ruta));
            }
        }
    }

    public function test_el_nombre_original_del_adjunto_no_queda_legible_en_la_base(): void
    {
        $paciente = $this->crearPaciente();
        $adjunto = $this->crearAdjunto($paciente, $this->crearProfesionalDelEquipo($paciente));

        // La fila cruda, sin pasar por el modelo: es lo que vería alguien que abre el archivo de la base.
        $fila = DB::table('media')->first();

        $this->assertStringNotContainsString('informe-tomas', $fila->file_name);
        $this->assertStringNotContainsString('informe-tomas', $fila->name);
        $this->assertStringNotContainsString('informe-tomas', $fila->custom_properties);

        // Pedido por el modelo vuelve el nombre original.
        $this->assertSame('informe-tomas.pdf', Mensaje::nombreAdjunto($adjunto));
    }

    // ---------------------------------------------------------------- Descarga

    public function test_un_profesional_del_equipo_descarga_el_adjunto_con_su_contenido_nombre_y_tipo_originales(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $adjunto = $this->crearAdjunto($paciente, $lucia);

        $respuesta = $this->actingAs($lucia)->get("/adjuntos/{$adjunto->id}");

        $respuesta->assertOk()
            ->assertDownload('informe-tomas.pdf')
            ->assertHeader('Content-Type', 'application/pdf');

        // Lo que se baja es el archivo original, ya descifrado.
        $this->assertSame(self::CONTENIDO, $respuesta->streamedContent());
    }

    public function test_un_usuario_que_no_es_del_equipo_no_puede_descargar_el_adjunto(): void
    {
        $paciente = $this->crearPaciente();
        $adjunto = $this->crearAdjunto($paciente, $this->crearProfesionalDelEquipo($paciente));
        $pedro = User::factory()->create(); // Tiene sesión, pero no atiende a este paciente.

        // Aunque conozca o adivine el link, no lo puede bajar.
        $this->actingAs($pedro)->get("/adjuntos/{$adjunto->id}")->assertForbidden();
    }

    public function test_sin_sesion_la_descarga_redirige_al_login(): void
    {
        $paciente = $this->crearPaciente();
        $adjunto = $this->crearAdjunto($paciente, $this->crearProfesionalDelEquipo($paciente));

        $this->get("/adjuntos/{$adjunto->id}")->assertRedirect('/login');
    }

    // ---------------------------------------------------------------- Elegir y quitar archivos

    public function test_elegir_archivos_dos_veces_los_acumula_en_vez_de_reemplazarlos(): void
    {
        $paciente = $this->crearPaciente();

        // El selector escribe en "nuevos" (propiedad de paso); updatedNuevos los pasa a "archivos" y la vacía.
        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('nuevos', [$this->pdf('informe.pdf')])
            ->assertCount('archivos', 1)
            ->assertSet('nuevos', [])
            // Segunda elección: se suma a la primera.
            ->set('nuevos', [UploadedFile::fake()->create('planilla.xlsx', 20), UploadedFile::fake()->create('dibujo.png', 20)])
            ->assertCount('archivos', 3)
            ->assertSet('nuevos', [])
            // Los elegidos se listan con su nombre, en el orden en que se eligieron.
            ->assertSeeInOrder(['informe.pdf', 'planilla.xlsx', 'dibujo.png']);
    }

    public function test_los_archivos_elegidos_en_dos_veces_se_guardan_todos_en_el_mensaje(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Van el informe y la planilla.')
            ->set('nuevos', [$this->pdf('informe.pdf')])
            ->set('nuevos', [UploadedFile::fake()->create('planilla.xlsx', 20)])
            ->call('enviar')
            ->assertHasNoErrors()
            // Después de enviar no queda ninguno elegido.
            ->assertSet('archivos', [])
            ->assertSet('nuevos', []);

        $nombres = Mensaje::firstOrFail()->getMedia('adjuntos')->map(fn (Media $adjunto) => Mensaje::nombreAdjunto($adjunto))->all();

        $this->assertSame(['informe.pdf', 'planilla.xlsx'], $nombres);
    }

    public function test_quitar_archivo_saca_solo_ese_de_los_elegidos(): void
    {
        $paciente = $this->crearPaciente();

        $formulario = Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('nuevos', [$this->pdf('informe.pdf'), UploadedFile::fake()->create('planilla.xlsx', 20), UploadedFile::fake()->create('dibujo.png', 20)])
            // Se saca el del medio (las posiciones arrancan en 0).
            ->call('quitarArchivo', 1)
            ->assertCount('archivos', 2)
            ->assertSee('informe.pdf')
            ->assertSee('dibujo.png')
            ->assertDontSee('planilla.xlsx');

        // La lista se vuelve a numerar desde 0: el que estaba tercero ahora es el segundo.
        $this->assertSame(['informe.pdf', 'dibujo.png'], array_map(fn ($archivo) => $archivo->getClientOriginalName(), $formulario->get('archivos')));

        // Una posición que no existe no rompe ni saca nada.
        $formulario->call('quitarArchivo', 9)->assertOk()->assertCount('archivos', 2);
    }

    public function test_el_archivo_quitado_no_se_guarda_y_su_copia_temporal_se_borra(): void
    {
        $paciente = $this->crearPaciente();
        $temporal = FileUploadConfiguration::storage();
        $carpeta = FileUploadConfiguration::directory();

        $formulario = Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Solo va la planilla.')
            ->set('nuevos', [$this->pdf(), UploadedFile::fake()->create('planilla.xlsx', 20)]);

        // Antes de quitarlo, el PDF está en claro en la carpeta temporal (si no, lo de abajo no probaría nada).
        $antes = collect($temporal->allFiles($carpeta))->map(fn ($ruta) => $temporal->get($ruta));
        $this->assertTrue($antes->contains(self::CONTENIDO), 'La subida tendría que dejar una copia temporal en claro.');

        $formulario->call('quitarArchivo', 0);

        // Al quitarlo se borra su copia temporal y el ".json" con su nombre: no hay que esperar al envío.
        foreach ($temporal->allFiles($carpeta) as $ruta) {
            $this->assertStringNotContainsString(self::CONTENIDO, $temporal->get($ruta));
            $this->assertStringNotContainsString('informe-tomas', $temporal->get($ruta));
        }

        $formulario->call('enviar')->assertHasNoErrors();

        $adjuntos = Mensaje::firstOrFail()->getMedia('adjuntos');

        $this->assertCount(1, $adjuntos);
        $this->assertSame('planilla.xlsx', Mensaje::nombreAdjunto($adjuntos[0]));
    }

    public function test_el_limite_de_5_archivos_vale_tambien_sumando_varias_elecciones(): void
    {
        $paciente = $this->crearPaciente();

        $formulario = Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Texto válido.');

        // De a dos por vez: tres elecciones dejan 6, uno más que el máximo.
        for ($i = 1; $i <= 3; $i++) {
            $formulario->set('nuevos', [UploadedFile::fake()->create("a-{$i}.pdf", 20), UploadedFile::fake()->create("b-{$i}.pdf", 20)]);
        }

        $formulario->call('enviar')->assertHasErrors(['archivos' => 'max']);

        $this->assertDatabaseCount('mensajes', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_el_selector_de_archivos_usa_la_propiedad_de_paso_y_solo_ofrece_los_tipos_permitidos(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeHtml('wire:model="nuevos"')
            // "accept" hace que el explorador de archivos muestre solo estas extensiones.
            ->assertSeeHtml('accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"');
    }

    // ---------------------------------------------------------------- Vista

    public function test_el_equipo_ve_el_adjunto_dentro_del_mensaje(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $adjunto = $this->crearAdjunto($paciente, $lucia);

        $html = Livewire::actingAs($lucia)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertSeeInOrder(['Adjunto el informe.', 'informe-tomas.pdf'])
            // La lista "Archivos" ya no la arma el historial: se mudó a la ficha.
            ->assertViewMissing('conArchivos')
            ->html();

        // En el historial el link de descarga aparece una sola vez: dentro del mensaje.
        $this->assertSame(1, substr_count($html, route('adjuntos.descargar', $adjunto)));
    }

    public function test_la_ficha_lista_los_archivos_del_paciente_para_el_equipo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente, 'Lucía Pérez');
        $adjunto = $this->crearAdjunto($paciente, $lucia);

        $html = Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertViewHas('conArchivos', fn ($mensajes) => $mensajes->count() === 1)
            ->assertSeeHtml('>Archivos</h2>')
            ->html();

        // El renglón de la lista: nombre del archivo, y debajo tamaño, quién lo subió y cuándo.
        $this->assertMatchesRegularExpression(
            '/wire:key="archivo-'.$adjunto->id.'".*?informe-tomas\.pdf.*?1 KB · Lucía Pérez · '.preg_quote(now()->format('d/m/Y'), '/').'/su',
            $html,
        );
    }

    public function test_la_lista_de_archivos_de_la_ficha_va_del_mas_nuevo_al_mas_viejo(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        $viejo = $this->crearAdjunto($paciente, $lucia, 'viejo.pdf');
        $nuevo = $this->crearAdjunto($paciente, $lucia, 'nuevo.pdf');
        // Se fija la fecha del primero bien atrás: el orden sale de la fecha del mensaje.
        Mensaje::whereKey($viejo->model_id)->update(['created_at' => '2026-01-10 10:00:00']);

        Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertSeeHtmlInOrder(['wire:key="archivo-'.$nuevo->id.'"', 'wire:key="archivo-'.$viejo->id.'"']);
    }

    public function test_sin_adjuntos_la_ficha_no_muestra_la_lista_de_archivos(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);
        Mensaje::create(['paciente_id' => $paciente->id, 'user_id' => $lucia->id, 'tipo' => 'mensaje', 'cuerpo' => 'Sin archivos.']);

        Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertViewHas('conArchivos', fn ($mensajes) => $mensajes->isEmpty())
            ->assertDontSeeHtml('>Archivos</h2>');
    }

    public function test_la_ficha_escucha_el_aviso_de_mensaje_enviado_para_refrescar_los_archivos(): void
    {
        $paciente = $this->crearPaciente();
        $lucia = $this->crearProfesionalDelEquipo($paciente);

        $ficha = Livewire::actingAs($lucia)
            ->test(Ficha::class, ['paciente' => $paciente])
            ->assertDontSeeHtml('>Archivos</h2>');

        // Llega un mensaje con adjunto (como si se hubiera enviado desde el historial) y el historial avisa.
        $adjunto = $this->crearAdjunto($paciente, $lucia);

        $ficha->dispatch('mensaje-enviado')
            ->assertSeeHtml('>Archivos</h2>')
            ->assertSeeHtml('wire:key="archivo-'.$adjunto->id.'"');
    }

    public function test_el_historial_avisa_a_la_ficha_cuando_se_envia_un_mensaje(): void
    {
        $paciente = $this->crearPaciente();

        Livewire::actingAs($this->crearProfesionalDelEquipo($paciente))
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->set('cuerpo', 'Adjunto el informe.')
            ->set('nuevos', [$this->pdf()])
            ->call('enviar')
            ->assertDispatched('mensaje-enviado');
    }

    public function test_un_usuario_que_no_es_del_equipo_no_ve_los_nombres_de_los_archivos(): void
    {
        $paciente = $this->crearPaciente();
        $this->crearAdjunto($paciente, $this->crearProfesionalDelEquipo($paciente));
        $pedro = User::factory()->create();

        // El nombre de un archivo puede decir mucho del paciente: tampoco se le muestra.
        Livewire::actingAs($pedro)
            ->test(Mensajes::class, ['paciente' => $paciente])
            ->assertDontSee('informe-tomas.pdf');
    }

    public function test_direccion_fuera_del_equipo_ve_la_ficha_pero_no_los_archivos(): void
    {
        $paciente = $this->crearPaciente();
        $adjunto = $this->crearAdjunto($paciente, $this->crearProfesionalDelEquipo($paciente));
        $this->entrarComoDireccion(); // Puede abrir la ficha, pero no está en el equipo.

        // Ni se le buscan: la lista llega vacía a la vista.
        Livewire::test(Ficha::class, ['paciente' => $paciente])
            ->assertOk()
            ->assertViewHas('esDelEquipo', false)
            ->assertViewHas('conArchivos', fn ($mensajes) => $mensajes->isEmpty())
            ->assertDontSeeHtml('>Archivos</h2>')
            ->assertDontSee('informe-tomas.pdf')
            ->assertDontSeeHtml(route('adjuntos.descargar', $adjunto));
    }
}
