<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('address_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Pivot table for pool-node relationship
        Schema::create('address_pool_node', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->unique(['address_pool_id', 'node_id']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address');  // IP address
            $table->unsignedTinyInteger('cidr');  // Subnet mask (e.g., 24)
            $table->string('gateway');
            $table->string('mac_address')->nullable();
            $table->enum('type', ['ipv4', 'ipv6'])->default('ipv4');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['address', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('address_pool_node');
        Schema::dropIfExists('address_pools');
    }
};
