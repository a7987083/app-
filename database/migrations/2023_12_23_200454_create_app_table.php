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
        Schema::create('app', function (Blueprint $table) {
            $table->id();
            $table->uuid('app_id')->unique();
            $table->string('app_bid');
            $table->string('app_name');
            $table->string('app_version');
            $table->text('app_introduction');
            $table->foreignId('class_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app');
    }
};
