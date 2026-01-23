<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Server permissions
            ['name' => 'View Own Servers', 'slug' => 'server.view.own', 'category' => 'server', 'description' => 'View own servers'],
            ['name' => 'View All Servers', 'slug' => 'server.view.all', 'category' => 'server', 'description' => 'View all servers (admin)'],
            ['name' => 'Create Servers', 'slug' => 'server.create', 'category' => 'server', 'description' => 'Create new servers'],
            ['name' => 'Edit Own Servers', 'slug' => 'server.edit.own', 'category' => 'server', 'description' => 'Edit own servers'],
            ['name' => 'Edit All Servers', 'slug' => 'server.edit.all', 'category' => 'server', 'description' => 'Edit any server (admin)'],
            ['name' => 'Delete Own Servers', 'slug' => 'server.delete.own', 'category' => 'server', 'description' => 'Delete own servers'],
            ['name' => 'Delete All Servers', 'slug' => 'server.delete.all', 'category' => 'server', 'description' => 'Delete any server (admin)'],
            ['name' => 'Rebuild Servers', 'slug' => 'server.rebuild', 'category' => 'server', 'description' => 'Rebuild servers'],
            ['name' => 'Server Power Controls', 'slug' => 'server.power', 'category' => 'server', 'description' => 'Start/stop/restart servers'],
            ['name' => 'Access Console', 'slug' => 'server.console', 'category' => 'server', 'description' => 'Access server console'],
            ['name' => 'Reset Password', 'slug' => 'server.reset-password', 'category' => 'server', 'description' => 'Reset server passwords'],

            // User management (admin only)
            ['name' => 'View Users', 'slug' => 'user.view', 'category' => 'user', 'description' => 'View user list'],
            ['name' => 'Create Users', 'slug' => 'user.create', 'category' => 'user', 'description' => 'Create new users'],
            ['name' => 'Edit Users', 'slug' => 'user.edit', 'category' => 'user', 'description' => 'Edit users'],
            ['name' => 'Delete Users', 'slug' => 'user.delete', 'category' => 'user', 'description' => 'Delete users'],

            // Node management (admin only)
            ['name' => 'View Nodes', 'slug' => 'node.view', 'category' => 'node', 'description' => 'View node list'],
            ['name' => 'Create Nodes', 'slug' => 'node.create', 'category' => 'node', 'description' => 'Add new nodes'],
            ['name' => 'Edit Nodes', 'slug' => 'node.edit', 'category' => 'node', 'description' => 'Edit nodes'],
            ['name' => 'Delete Nodes', 'slug' => 'node.delete', 'category' => 'node', 'description' => 'Delete nodes'],

            // Template management (admin only)
            ['name' => 'View Templates', 'slug' => 'template.view', 'category' => 'template', 'description' => 'View template list'],
            ['name' => 'Create Templates', 'slug' => 'template.create', 'category' => 'template', 'description' => 'Create templates'],
            ['name' => 'Edit Templates', 'slug' => 'template.edit', 'category' => 'template', 'description' => 'Edit templates'],
            ['name' => 'Delete Templates', 'slug' => 'template.delete', 'category' => 'template', 'description' => 'Delete templates'],

            // IP management (admin only)
            ['name' => 'View IP Pools', 'slug' => 'ip.view', 'category' => 'ip', 'description' => 'View IP address pools'],
            ['name' => 'Create IPs', 'slug' => 'ip.create', 'category' => 'ip', 'description' => 'Add IP addresses'],
            ['name' => 'Assign IPs', 'slug' => 'ip.assign', 'category' => 'ip', 'description' => 'Assign IPs to servers'],

            // System settings (admin only)
            ['name' => 'System Settings', 'slug' => 'system.settings', 'category' => 'system', 'description' => 'Modify system settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}
