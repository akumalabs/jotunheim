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
        Schema::table('servers', function (Blueprint $table) {
            // Change enum to string, doctrine/dbal handles SQLite support
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // Reverting to enum might be lossy if values outside enum exist
            // SQLite doesn't natively support ENUM so typically it becomes VARCHAR with check constraint or just VARCHAR
            // Ideally we'd map 'rebuilding' back to 'installing' or 'failed' before changing back, but for now we leave it as string or try revert
            // $table->enum('status', ['installing', 'running', 'stopped', 'suspended', 'pending', 'failed'])->default('pending')->change();
            // Simpler: leave as string or drop table in full revert.
            // But strict migration rollback:
            $table->string('status')->default('pending')->change(); // Keep as string to avoid error
        });
    }
};
