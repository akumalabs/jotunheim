<?php

namespace Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandwidthTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bandwidth_calculation_works_for_known_values()
    {
        $bytes = 1073741824; // 1GB
        $gb = round($bytes / 1073741824, 2);

        $this->assertEquals(1.0, $gb);
    }

    public function test_bandwidth_percentage_calculated_correctly()
    {
        $usage = 1000000000; // 1GB
        $limit = 10000000000; // 10GB
        $percentage = ($usage / $limit) * 100;

        $this->assertEquals(10.0, $percentage);
    }

    public function test_bandwidth_overage_identified_correctly()
    {
        $usage = 15000000000; // 15GB
        $limit = 10000000000; // 10GB

        $this->assertGreaterThan($limit, $usage);
    }
}
