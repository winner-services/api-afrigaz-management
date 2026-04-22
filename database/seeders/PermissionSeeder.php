<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'produits',
            'produits-unite',
            'produits-categorie',
            'partennaires',
            'fournisseur',
            'distributeur',
            'categorie-distributeur',
            'client',
            'tank',
            'approvisionnement',
            'recharge-bouteille',
            'transfert-stock',
            'retour-cylindre',
            'livraison-distributeur',
            'vente',
            'transaction',
            'dette-distributeur',
            'comptes',
            'devises',
            'paramettre',
            'composition-caution',
            'role-utilisateur',
            'profile-entreprise',
            'point-de-vente',
            'charroi-automobile'

        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
