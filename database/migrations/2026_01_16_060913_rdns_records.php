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
        Schema::create('rdns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->string('ip_address', 45);
            $table->string('ptr_record')->nullable();
            $table->enum('mode', ['manual', 'automated'])->default('manual');
            $table->boolean('verified')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
