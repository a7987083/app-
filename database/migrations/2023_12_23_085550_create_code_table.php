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
        Schema::create('code', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('udid', 40)->nullable();
            $table->string('devices_id', 10)->nullable();
            $table->string('remark')->nullable();
            $table->enum('status', ['ENABLED', 'DISABLED'])->default('ENABLED');
            $table->integer('after_sale_day')->digits(5);
            $table->integer('use_after_sale')->digits(5)->nullable();
            $table->integer('after_sale_num')->digits(5);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('maturity_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code');
    }
};
