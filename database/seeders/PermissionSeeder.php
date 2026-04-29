<?php

namespace Database\Seeders;

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
            'tableau-de-bord',
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
            'finance',
            'payement-dette',
            'transaction',
            'dette-distributeur',
            'comptes',
            'devises',
            'paramettre',
            'utilisateur',
            'bonus-client',
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
