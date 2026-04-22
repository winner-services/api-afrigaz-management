<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryProdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'gaz',
            'bouteille',
        ];
        ProductCategory::create([
            'designation' => 'gaz',
        ]);
        foreach ($categories as $index => $categorie) {
            ProductCategory::create([
                'designation' => $categorie
            ]);
        }
    }
}
