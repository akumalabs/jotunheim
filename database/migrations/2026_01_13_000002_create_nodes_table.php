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
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('fqdn');  // Fully qualified domain name
            $table->unsignedInteger('port')->default(8006);  // Proxmox API port
            $table->text('token_id')->nullable();  // Proxmox API token ID
            $table->text('token_secret')->nullable();  // Proxmox API token secret (encrypted)
            $table->unsignedBigInteger('memory')->default(0);  // Total memory in bytes
            $table->unsignedBigInteger('memory_overallocate')->default(0);  // Percentage
            $table->unsignedBigInteger('disk')->default(0);  // Total disk in bytes
            $table->unsignedBigInteger('disk_overallocate')->default(0);  // Percentage
            $table->unsignedInteger('cpu')->default(0);  // Total CPU cores
            $table->unsignedInteger('cpu_overallocate')->default(0);  // Percentage
            $table->string('storage')->default('local');  // Default storage identifier
            $table->string('network')->default('vmbr0');  // Default network bridge
            $table->string('cluster')->nullable();  // Proxmox cluster name
            $table->boolean('maintenance_mode')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
