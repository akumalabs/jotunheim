<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Full system access with all permissions',
                'priority' => 100,
                'is_system' => true,
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Standard user with access to own servers only',
                'priority' => 10,
                'is_system' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::create($roleData);

            // Administrator gets ALL permissions
            if ($role->slug === 'administrator') {
                $role->permissions()->attach(Permission::all());
            }

            // User gets limited permissions
            if ($role->slug === 'user') {
                $userPermissions = Permission::whereIn('slug', [
                    'server.view.own',
                    'server.create',
                    'server.edit.own',
                    'server.delete.own',
                    'server.rebuild',
                    'server.power',
                    'server.console',
                    'server.reset-password',
                ])->get();
                $role->permissions()->attach($userPermissions);
            }
        }
    }
}
