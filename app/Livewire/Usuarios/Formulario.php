<?php

namespace App\Livewire\Usuarios;

use App\Livewire\Concerns\SoloDireccion;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Formulario de usuario (un profesional). Sirve para crear (/administracion/profesionales/crear)
 * y para editar (/administracion/profesionales/{usuario}/editar): depende de si $usuarioId tiene valor.
 * Los usuarios no se borran.
 *
 * Vista: resources/views/livewire/usuarios/formulario.blade.php
 */
class Formulario extends Component
{
    use SoloDireccion; // Solo Dirección puede usar esta pantalla (ver docs/roles.md).

    /** Contraseña que viene precargada al crear un usuario. Se puede cambiar ahí mismo; después cada uno la cambia en Mi perfil. */
    public const CONTRASENA_INICIAL = 'password';

    // Id del usuario que se está editando. null = se está creando uno nuevo.
    public ?int $usuarioId = null;

    // Conectadas a los campos de la vista con wire:model.
    public string $name = '';

    public string $email = '';

    /** Teléfono o WhatsApp de contacto. Opcional y sin cifrar (es contacto laboral; ver docs/cifrado.md). */
    public string $telefono = '';

    /**
     * Contraseña. Al crear viene precargada con CONTRASENA_INICIAL y es obligatoria.
     * Al editar arranca vacía: si se deja así, no se toca; si se escribe algo, pasa a ser la nueva.
     */
    public string $password = '';

    /** ¿Es administrador? Tildado = rol Dirección; sin tildar = rol Profesional (ver docs/roles.md). */
    public bool $admin = false;

    /** @var array<int, string> Ids de las disciplinas tildadas. Vacío = sin disciplina. */
    public array $disciplinas = [];

    /** Al abrir la pantalla: si la URL trae un usuario, carga sus datos en el formulario. */
    public function mount(?User $usuario = null): void
    {
        if (! $usuario?->exists) {
            $this->password = self::CONTRASENA_INICIAL; // Se está creando: solo viene precargada la contraseña.

            return;
        }

        $this->usuarioId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->telefono = $usuario->telefono ?? '';
        $this->admin = $usuario->esDireccion();
        // Como texto, porque así los manda el navegador y así se comparan con las casillas.
        $this->disciplinas = $usuario->disciplinas->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Se ejecuta al enviar el formulario: valida, crea o actualiza el usuario y vuelve al listado. */
    public function guardar()
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->telefono = trim($this->telefono);

        $datos = $this->validate(
            [
                'name' => ['required', 'max:255'],
                // Único en la tabla, sin contar al usuario que se está editando.
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->usuarioId)],
                'telefono' => ['nullable', 'max:50'],
                // Obligatoria al crear; al editar se puede dejar vacía para no cambiarla.
                'password' => [$this->usuarioId ? 'nullable' : 'required', 'min:8', 'max:255'],
                'admin' => ['boolean', function (string $campo, mixed $valor, \Closure $fallar) {
                    // El centro no puede quedar sin ningún administrador: nadie podría cargar usuarios ni pacientes.
                    if (! $valor && $this->esLaUnicaDireccion()) {
                        $fallar('Es la única persona administradora: antes hay que hacer administradora a otra.');
                    }
                }],
                'disciplinas' => ['array'],
                'disciplinas.*' => ['exists:disciplinas,id'],
            ],
            [
                'required' => 'Este campo es obligatorio.',
                'max' => 'El texto es demasiado largo.',
                'email' => 'Ingresá un email válido.',
                'unique' => 'Ya existe un usuario con ese email.',
                'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
                'disciplinas.*.exists' => 'Una de las disciplinas elegidas no existe.',
            ],
        );

        // El rol y las disciplinas no son columnas del usuario: se guardan aparte.
        $rol = $datos['admin'] ? 'direccion' : 'profesional';
        $disciplinas = $datos['disciplinas'];
        unset($datos['admin'], $datos['disciplinas']);

        // El teléfono vacío se guarda como null.
        $datos['telefono'] = $datos['telefono'] !== '' ? $datos['telefono'] : null;

        // Editando con la contraseña en blanco: se saca de los datos para no pisar la que tiene.
        if ($datos['password'] === '') {
            unset($datos['password']);
        }

        // La contraseña se encripta sola al guardarse (ver User::casts).
        if ($this->usuarioId) {
            $usuario = User::findOrFail($this->usuarioId);
            $usuario->update($datos);
        } else {
            $usuario = User::create($datos);
        }

        // syncRoles deja al usuario con ese único rol.
        $usuario->syncRoles($rol);
        // sync deja exactamente las tildadas: agrega las nuevas y saca las destildadas.
        $usuario->disciplinas()->sync($disciplinas);

        return $this->redirectRoute('usuarios');
    }

    /** ¿El usuario que se está editando es hoy la única persona con rol Dirección? */
    private function esLaUnicaDireccion(): bool
    {
        return $this->usuarioId !== null
            && User::findOrFail($this->usuarioId)->esDireccion()
            && User::role('direccion')->count() === 1;
    }

    public function render()
    {
        return view('livewire.usuarios.formulario', [
            // Para las casillas. No se llama "disciplinas" porque ese nombre ya lo usa la propiedad de arriba.
            'todasLasDisciplinas' => Disciplina::orderBy('nombre')->get(),
        ]);
    }
}
