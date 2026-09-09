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
        Schema::create('sign_log', function (Blueprint $table) {
            $table->uuid('id')->unique();
            $table->uuid('app_id');
            $table->string('app_bid');
            $table->string('app_name');
            $table->string('app_version');
            $table->integer('multiple_num')->digits(5);
            $table->integer('multiple_init')->digits(5);
            $table->string('udid', 40)->nullable();
            $table->string('cert_id', 10);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sign_log');
    }
};
