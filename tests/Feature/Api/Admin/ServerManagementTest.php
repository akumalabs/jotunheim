<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    public function test_admin_can_list_servers()
    {
        $node = Node::create([
            'name' => 'Test Node',
            'hostname' => 'node1.test.com',
            'port' => 8006,
            'location_id' => 1,
        ]);

        $user = User::factory()->create();

        Server::factory()->count(5)->create([
            'user_id' => $user->id,
            'node_id' => $node->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/servers');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_create_server()
    {
        $node = Node::create([
            'name' => 'Test Node',
            'hostname' => 'node1.test.com',
            'port' => 8006,
            'location_id' => 1,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/admin/servers', [
                'name' => 'Test Server',
                'user_id' => $user->id,
                'node_id' => $node->id,
                'memory' => 2048,
                'disk' => 50,
                'cpu' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'uuid',
                ],
            ]);

        $this->assertDatabaseHas('servers', [
            'name' => 'Test Server',
            'user_id' => $user->id,
            'node_id' => $node->id,
        ]);
    }

    public function test_non_admin_cannot_access_admin_routes()
    {
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $regularUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/servers');

        $response->assertStatus(403);
    }
}
