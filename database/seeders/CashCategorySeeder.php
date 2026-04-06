<?php

namespace Database\Seeders;

use App\Models\CashCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CashCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Vente des produits',
            'Achat des produits'
        ];

        $types = [
            'Revenue',
            'Depense',
        ];

        foreach ($categories as $index => $categorie) {
            CashCategory::create([
                'designation' => $categorie,
                'type' => $types[$index],
                'addedBy' => 1
            ]);
        }
    }
}
