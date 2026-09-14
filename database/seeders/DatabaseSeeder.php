<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->owner()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(VehiculoSeeder::class);
        $this->call(EntregaSeeder::class);
        $this->call(ServicioSeeder::class);

        /* La agenda del taller no es una tabla de datos: son los horarios que
           le cuelgan a cada puesto. Los crea el comando, que es idempotente. */
        Artisan::call('taller:agenda');
    }
}
