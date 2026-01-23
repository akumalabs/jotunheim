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
        if (! Schema::hasTable('template_groups')) {
            Schema::create('template_groups', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('node_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->boolean('hidden')->default(false);
                $table->integer('order_column')->default(0);
                $table->timestamps();
            });
        }

        // Add template_group_id to templates table if not exists
        if (! Schema::hasColumn('templates', 'template_group_id')) {
            Schema::table('templates', function (Blueprint $table) {
                $table->foreignId('template_group_id')->nullable()->after('node_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('templates', 'order_column')) {
            Schema::table('templates', function (Blueprint $table) {
                $table->integer('order_column')->default(0)->after('visible');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_groups');
    }
};
