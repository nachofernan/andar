<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Todo usuario tiene un rol: el que se crea con la factory (en los tests) nace como "profesional",
     * salvo que se pida ->direccion().
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $usuario) {
            if (! $usuario->roles()->exists()) {
                $usuario->assignRole('profesional');
            }
        });
    }

    /** Crea al usuario con rol Dirección: User::factory()->direccion()->create(). */
    public function direccion(): static
    {
        return $this->afterCreating(fn (User $usuario) => $usuario->syncRoles('direccion'));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
