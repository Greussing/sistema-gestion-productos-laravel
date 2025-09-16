<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategoriaSeeder::class,
            UserSeeder::class,
            ProductoSeeder::class, // 👈 aquí añadimos el seeder de usuarios
        ]);
    }
}