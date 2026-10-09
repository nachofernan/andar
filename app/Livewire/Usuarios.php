<?php

namespace App\Livewire;

use App\Livewire\Concerns\SoloDireccion;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Pantalla de usuarios (los profesionales): lista los que hay y permite crear y editar (no borrar).
 *
 * El mismo formulario sirve para crear y para editar: depende de si $editandoId tiene valor.
 * Vista: resources/views/livewire/usuarios.blade.php
 */
class Usuarios extends Component
{
    use SoloDireccion; // Solo Dirección puede usar esta pantalla (ver docs/roles.md).

    /** Contraseña con la que nace todo usuario nuevo. Provisorio, hasta que exista el cambio de contraseña. */
    public const CONTRASENA_INICIAL = 'password';

    // Conectadas a los campos de la vista con wire:model.
    public string $name = '';

    public string $email = '';

    // Uno de User::ROLES. Un usuario nuevo arranca como profesional.
    public string $rol = 'profesional';

    /** @var array<int, string> Ids de las disciplinas tildadas. Vacío = sin disciplina. */
    public array $disciplinas = [];

    // Id del usuario que se está editando. null = se está creando uno nuevo.
    public ?int $editandoId = null;

    /** Se ejecuta al enviar el formulario: valida y crea o actualiza el usuario. */
    public function guardar(): void
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);

        $datos = $this->validate(
            [
                'name' => ['required', 'max:255'],
                // Único en la tabla, sin contar al usuario que se está editando.
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editandoId)],
                'rol' => ['required', Rule::in(array_keys(User::ROLES)), function (string $campo, mixed $valor, \Closure $fallar) {
                    // El centro no puede quedar sin nadie de Dirección: nadie podría cargar usuarios ni pacientes.
                    if ($valor !== 'direccion' && $this->esLaUnicaDireccion()) {
                        $fallar('Es la única persona de Dirección: antes hay que darle ese rol a otra.');
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
                'rol.in' => 'Elegí un rol de la lista.',
                'disciplinas.*.exists' => 'Una de las disciplinas elegidas no existe.',
            ],
        );

        // El rol y las disciplinas no son columnas del usuario: se guardan aparte.
        $rol = $datos['rol'];
        $disciplinas = $datos['disciplinas'];
        unset($datos['rol'], $datos['disciplinas']);

        if ($this->editandoId) {
            $usuario = User::findOrFail($this->editandoId);
            $usuario->update($datos); // Al editar no se toca la contraseña.
        } else {
            $usuario = User::create($datos + ['password' => self::CONTRASENA_INICIAL]); // Se encripta sola (ver User::casts).
        }

        // syncRoles deja al usuario con ese único rol.
        $usuario->syncRoles($rol);
        // sync deja exactamente las tildadas: agrega las nuevas y saca las destildadas.
        $usuario->disciplinas()->sync($disciplinas);

        $this->cancelar();
    }

    /** ¿El usuario que se está editando es hoy la única persona con rol Dirección? */
    private function esLaUnicaDireccion(): bool
    {
        return $this->editandoId !== null
            && User::findOrFail($this->editandoId)->esDireccion()
            && User::role('direccion')->count() === 1;
    }

    /** Carga un usuario en el formulario para editarlo. */
    public function editar(int $id): void
    {
        $usuario = User::findOrFail($id);

        $this->resetValidation();
        $this->editandoId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->rol = $usuario->rol ?? 'profesional';
        // Como texto, porque así los manda el navegador y así se comparan con las casillas.
        $this->disciplinas = $usuario->disciplinas->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Vacía el formulario y lo deja listo para crear uno nuevo. */
    public function cancelar(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.usuarios', [
            // with(...) trae las disciplinas y el rol de todos en una sola consulta cada uno.
            'usuarios' => User::with('disciplinas', 'roles')->orderBy('name')->get(),
            // Para las casillas. No se llama "disciplinas" porque ese nombre ya lo usa la propiedad de arriba.
            'todasLasDisciplinas' => Disciplina::orderBy('nombre')->get(),
            'roles' => User::ROLES, // Para el desplegable.
        ]);
    }
}
