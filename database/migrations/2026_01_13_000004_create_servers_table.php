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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('vmid');  // Proxmox VM ID
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->text('description')->nullable();

            // Resources
            $table->unsignedInteger('cpu')->default(1);  // CPU cores
            $table->unsignedBigInteger('memory')->default(1073741824);  // Memory in bytes (1GB default)
            $table->unsignedBigInteger('disk')->default(10737418240);  // Disk in bytes (10GB default)
            $table->unsignedBigInteger('bandwidth_limit')->nullable();  // Monthly bandwidth limit in bytes
            $table->unsignedBigInteger('bandwidth_usage')->default(0);  // Current month usage

            // Status
            $table->enum('status', [
                'installing',
                'running',
                'stopped',
                'suspended',
                'pending',
                'failed',
            ])->default('pending');

            // Flags
            $table->boolean('is_suspended')->default(false);
            $table->boolean('is_installing')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->index(['node_id', 'vmid']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
