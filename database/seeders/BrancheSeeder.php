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
            'address' => 'Comm. Beu',
            'addedBy' => 1
        ]);
    }
}
