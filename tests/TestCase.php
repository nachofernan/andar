<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los tests no cargan el CSS/JS real, así no dependen de haber corrido `npm run build`.
        $this->withoutVite();
    }

    /**
     * Crea un usuario de Dirección y deja su sesión iniciada para el resto del test
     * (vale tanto para $this->get(...) como para Livewire::test(...)). Devuelve ese usuario.
     *
     * Se usa en los tests de las pantallas de gestión (disciplinas, usuarios, crear y editar pacientes),
     * que solo puede usar Dirección. Ojo: suma un usuario a la tabla users.
     *
     * @param  array<string, mixed>  $datos  Campos que se quieran fijar (por ejemplo el nombre).
     */
    protected function entrarComoDireccion(array $datos = []): User
    {
        $direccion = User::factory()->direccion()->create($datos);

        $this->actingAs($direccion);

        return $direccion;
    }
}
