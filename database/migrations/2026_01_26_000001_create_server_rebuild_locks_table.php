<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_rebuild_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->timestamp('locked_at');
            $table->timestamp('expires_at');
            $table->unique('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_rebuild_locks');
    }
};
