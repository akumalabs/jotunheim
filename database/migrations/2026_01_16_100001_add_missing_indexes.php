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
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('type');
            $table->index('server_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('subject_id');
            $table->index('subject_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('type');
            $table->dropIndex('server_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('user_id');
            $table->dropIndex('subject_id');
            $table->dropIndex('subject_type');
        });
    }
};
