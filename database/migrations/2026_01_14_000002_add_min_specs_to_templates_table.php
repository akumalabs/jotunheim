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
        Schema::table('templates', function (Blueprint $table) {
            // Minimum resource requirements for this template
            $table->unsignedInteger('min_cpu')->default(1)->after('vmid');
            $table->unsignedBigInteger('min_memory')->default(536870912)->after('min_cpu'); // 512MB in bytes
            $table->unsignedBigInteger('min_disk')->default(1073741824)->after('min_memory'); // 1GB in bytes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['min_cpu', 'min_memory', 'min_disk']);
        });
    }
};
