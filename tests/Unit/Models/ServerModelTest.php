<?php

namespace Tests\Unit\Models;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_has_fillable_fields()
    {
        $server = new Server;

        $this->assertIsArray($server->getFillable());
        $this->assertContains('name', $server->getFillable());
        $this->assertContains('user_id', $server->getFillable());
        $this->assertContains('node_id', $server->getFillable());
        $this->assertContains('memory', $server->getFillable());
        $this->assertContains('disk', $server->getFillable());
        $this->assertContains('cpu', $server->getFillable());
        $this->assertContains('status', $server->getFillable());
    }

    public function test_server_has_proper_casts()
    {
        $server = new Server;

        $this->assertIsArray($server->getCasts());
        $this->assertContains('memory', array_keys($server->getCasts()));
        $this->assertContains('disk', array_keys($server->getCasts()));
        $this->assertContains('cpu', array_keys($server->getCasts()));
        $this->assertContains('is_suspended', array_keys($server->getCasts()));
    }

    public function test_server_uuid_is_generated_on_creation()
    {
        $server = Server::create([
            'name' => 'Test Server',
            'user_id' => 1,
            'node_id' => 1,
            'memory' => 2048,
            'disk' => 50,
            'cpu' => 2,
            'status' => 'stopped',
        ]);

        $this->assertNotNull($server->uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $server->uuid);
    }

    public function test_formatted_memory_attribute_works()
    {
        $server = new Server(['memory' => 2147483648]); // 2GB in bytes

        $this->assertEquals('2 GB', $server->formatted_memory);
    }

    public function test_formatted_disk_attribute_works()
    {
        $server = new Server(['disk' => 10737418240]); // 10GB in bytes

        $this->assertEquals('10 GB', $server->formatted_disk);
    }

    public function test_is_running_helper_works()
    {
        $runningServer = new Server(['status' => 'running']);
        $stoppedServer = new Server(['status' => 'stopped']);

        $this->assertTrue($runningServer->isRunning());
        $this->assertFalse($stoppedServer->isRunning());
    }

    public function test_is_stopped_helper_works()
    {
        $runningServer = new Server(['status' => 'running']);
        $stoppedServer = new Server(['status' => 'stopped']);

        $this->assertFalse($runningServer->isStopped());
        $this->assertTrue($stoppedServer->isStopped());
    }
}
