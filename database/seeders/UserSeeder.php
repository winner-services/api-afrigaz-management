<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::find(1);

        if (!$role) {
            throw new \Exception("Le rôle avec ID 1 n'existe pas");
        }

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@admin.com',
            'phone' => '+243990000000',
            'password' => Hash::make('admin'),
            'active' => true,
            'status' => 'created',
        ]);
        $user->assignRole($role);
    }
}
