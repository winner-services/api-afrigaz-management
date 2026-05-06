<?php

namespace Database\Seeders;

use App\Models\Branche;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrancheSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branche::create([
            'name' => 'Entrepôt principal',
            'city' => 'Beni',
            'commune' => 'Comm. Beu',
            'quartier' => 'Beu',
            'avenue' => 'Boulevard Nyamwisi',
            'email' => 'comtact@gmail.com',
            'reference' => fake()->unique()->numerify('PT-AFGZ-#####'),
            'addedBy' => 1
        ]);
    }
}
