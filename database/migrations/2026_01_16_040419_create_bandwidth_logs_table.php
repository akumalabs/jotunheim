<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bandwidth_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->unsignedInteger('bytes_in')->default(0);
            $table->unsignedInteger('bytes_out')->default(0);
            $table->unsignedInteger('total_bytes')->default(0);
            $table->timestamp('logged_at');
            $table->index(['server_id', 'logged_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bandwidth_logs');
    }

    /**
     * Reset monthly bandwidth for all servers.
     */
    public function resetMonthlyBandwidth(): void
    {
        DB::table('servers')->update(['bandwidth_usage' => 0]);
    }
};
