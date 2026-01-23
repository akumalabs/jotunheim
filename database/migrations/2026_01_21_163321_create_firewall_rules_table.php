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
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable();
            $table->integer('priority')->default(515);
            $table->enum('direction', ['in', 'out', 'both'])->default('both');
            $table->enum('action', ['allow', 'deny'])->default('allow');
            $table->enum('protocol', ['tcp', 'udp', 'icmp', 'all'])->default('all');
            $table->string('source_address')->nullable();
            $table->integer('source_port')->nullable();
            $table->string('dest_address')->nullable();
            $table->integer('dest_port')->nullable();
            $table->enum('ip_version', ['ipv4', 'ipv6', 'both'])->default('ipv4');
            $table->boolean('enabled')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
            
            $table->index(['server_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
