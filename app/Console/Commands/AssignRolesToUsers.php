<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;

class AssignRolesToUsers extends Command
{
    protected $signature = 'rbac:assign-roles';
    protected $description = 'Assign roles to existing users based on is_admin field';

    public function handle(): int
    {
        $this->info('Assigning roles to existing users...');

        $adminRole = Role::where('slug', 'administrator')->first();
        $userRole = Role::where('slug', 'user')->first();

        if (!$adminRole || !$userRole) {
            $this->error('Roles not found. Please run seeders first: php artisan db:seed --class=PermissionSeeder && php artisan db:seed --class=RoleSeeder');
            return 1;
        }

        // Assign administrator role to admins
        $admins = User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            if (!$admin->roles()->where('slug', 'administrator')->exists()) {
                $admin->roles()->attach($adminRole);
                $admin->primary_role_id = $adminRole->id;
                $admin->save();
                $this->info("✓ Assigned 'administrator' role to: {$admin->email}");
            } else {
                $this->comment("○ {$admin->email} already has administrator role");
            }
        }

        // Assign user role to non-admins
        $users = User::where('is_admin', false)->get();
        foreach ($users as $user) {
            if (!$user->roles()->where('slug', 'user')->exists()) {
                $user->roles()->attach($userRole);
                $user->primary_role_id = $userRole->id;
                $user->save();
                $this->info("✓ Assigned 'user' role to: {$user->email}");
            } else {
                $this->comment("○ {$user->email} already has user role");
            }
        }

        $this->newLine();
        $this->info('✓ Role assignment complete!');
        $this->info("Administrators: {$admins->count()}");
        $this->info("Users: {$users->count()}");

        return 0;
    }
}
