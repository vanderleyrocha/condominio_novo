<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PapelUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ParametrosSeeder::class,
        ]);

        // Usuário administrador inicial (senha deve ser trocada no primeiro acesso)
        User::query()->firstOrCreate(
            ['name' => 'admin'],
            [
                'email' => 'admin@condominio.local',
                'password' => 'trocar-esta-senha',
                'papel' => PapelUsuario::Admin,
            ],
        );
    }
}
