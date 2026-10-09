<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Crea los dos roles de la app: "direccion" y "profesional" (ver docs/roles.md).
 * Van en una migración (y no en los datos de ejemplo) porque tienen que existir en toda instalación.
 * Las tablas de roles ya las creó la migración del paquete Spatie Permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['direccion', 'profesional'] as $rol) {
            Role::findOrCreate($rol);
        }
    }

    public function down(): void
    {
        Role::whereIn('name', ['direccion', 'profesional'])->delete();
    }
};
