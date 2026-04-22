<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesPermissions = [
            'admin' => [
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
            ]
        ];
        $actions = ['voir', 'ajouter', 'modifier', 'supprimer'];

        foreach ($rolesPermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $permissionsModels = Permission::whereIn('name', $permissions)->get();

            $role->syncPermissions($permissionsModels);

            foreach ($permissionsModels as $perm) {
                DB::table('role_permission_actions')->updateOrInsert(
                    [
                        'role_id'       => $role->id,
                        'permission_id' => $perm->id,
                    ],
                    [
                        'voir'      => true,
                        'ajouter'   => $roleName === 'admin',
                        'modifier' => $roleName === 'admin',
                        'supprimer' => $roleName === 'admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
