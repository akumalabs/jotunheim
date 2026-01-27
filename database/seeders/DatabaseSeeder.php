<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user with default password
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@jotunheim.local',
            'password' => Hash::make('Password123!'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        // Create demo user with default password
        $user = User::create([
            'name' => 'Demo User',
            'email' => 'user@jotunheim.local',
            'password' => Hash::make('Password123!'),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        // Create sample location
        $location = Location::create([
            'name' => 'Amsterdam',
            'short_code' => 'AMS',
            'description' => 'Primary datacenter in Amsterdam, Netherlands',
        ]);

        // Note: Nodes should be added via the UI with real Proxmox credentials
        // This is just a placeholder for testing without real Proxmox
        // Uncomment below to add a dummy node:
        /*
        Node::create([
            'location_id' => $location->id,
            'name' => 'proxmox-01',
            'fqdn' => 'proxmox.example.com',
            'port' => 8006,
            'token_id' => 'root@pam!jotunheim',
            'token_secret' => 'your-token-secret-here',
            'memory' => 68719476736, // 64GB
            'disk' => 1099511627776, // 1TB
            'cpu' => 16,
            'storage' => 'local-lvm',
            'network' => 'vmbr0',
        ]);
        */

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin login: admin@jotunheim.local');
        $this->command->info('Admin password: Password123!');
        $this->command->info('User login: user@jotunheim.local');
        $this->command->info('User password: Password123!');
        $this->command->warn('IMPORTANT: Change these passwords immediately after first login!');
    }
}
