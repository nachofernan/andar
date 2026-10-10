<?php

namespace App\Livewire\Disciplinas;

use App\Livewire\Concerns\SoloDireccion;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Formulario de disciplina. Sirve para crear (/administracion/disciplinas/crear)
 * y para editar (/administracion/disciplinas/{disciplina}/editar): depende de si $disciplinaId tiene valor.
 * Las disciplinas no se borran. El listado está en la pantalla "Profesionales y disciplinas" (Usuarios\Lista).
 *
 * Vista: resources/views/livewire/disciplinas/formulario.blade.php
 */
class Formulario extends Component
{
    use SoloDireccion; // Solo Dirección puede usar esta pantalla (ver docs/roles.md).

    // Id de la disciplina que se está editando. null = se está creando una nueva.
    public ?int $disciplinaId = null;

    // Conectadas a los campos de la vista con wire:model.
    public string $nombre = '';

    /** Color de la disciplina en hexa (ej. #7c5cf0). Una nueva arranca con el violeta de la app. */
    public string $color = Disciplina::COLOR_INICIAL;

    /** @var array<int, string> Ids de los profesionales tildados: los que tienen esta disciplina. */
    public array $profesionales = [];

    /** Al abrir la pantalla: si la URL trae una disciplina, carga sus datos en el formulario. */
    public function mount(?Disciplina $disciplina = null): void
    {
        if (! $disciplina?->exists) {
            return; // Se está creando: el formulario queda vacío.
        }

        $this->disciplinaId = $disciplina->id;
        $this->nombre = $disciplina->nombre;
        $this->color = $disciplina->color;
        // Como texto, porque así los manda el navegador y así se comparan con las casillas.
        $this->profesionales = $disciplina->profesionales->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Se ejecuta al enviar el formulario: valida, guarda la disciplina y quiénes la tienen, y vuelve al listado. */
    public function guardar()
    {
        $this->nombre = trim($this->nombre); // Sin espacios de más, para que "Psicología " no cuente como otra.
        $this->color = strtolower(trim($this->color)); // Siempre en minúsculas: #7C5CF0 y #7c5cf0 son el mismo color.

        $datos = $this->validate(
            [
                // Único en la tabla, sin contar la que se está editando (si no, no se podría guardar sin cambiar el nombre).
                'nombre' => ['required', 'max:255', Rule::unique('disciplinas', 'nombre')->ignore($this->disciplinaId)],
                // Hexa completo: numeral y seis caracteres entre 0-9 y a-f.
                'color' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
                'profesionales' => ['array'],
                'profesionales.*' => ['exists:users,id'],
            ],
            [
                'required' => 'Este campo es obligatorio.',
                'max' => 'El nombre es demasiado largo.',
                'unique' => 'Ya existe una disciplina con ese nombre.',
                'color.regex' => 'Escribí el color en hexa, por ejemplo #7c5cf0.',
                'profesionales.*.exists' => 'Uno de los profesionales elegidos no existe.',
            ],
        );

        // Los profesionales no son una columna de la disciplina: se guardan aparte, en la tabla disciplina_user.
        $profesionales = $datos['profesionales'];
        unset($datos['profesionales']);

        $disciplina = $this->disciplinaId ? Disciplina::findOrFail($this->disciplinaId) : new Disciplina;
        $disciplina->fill($datos)->save();

        // sync deja exactamente a los tildados: agrega los nuevos y saca los destildados.
        $disciplina->profesionales()->sync($profesionales);

        return $this->redirectRoute('usuarios');
    }

    public function render()
    {
        return view('livewire.disciplinas.formulario', [
            // Todos los usuarios, para las casillas. No se llama "profesionales" porque ese nombre ya lo usa la propiedad de arriba.
            'usuarios' => User::orderBy('name')->get(),
        ]);
    }
}
