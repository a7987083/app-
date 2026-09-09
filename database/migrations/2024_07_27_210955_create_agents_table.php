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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('remark')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->decimal('credit', 12, 2)->default(0.00);
            $table->decimal('price', 12, 2)->default(68.00);
            $table->enum('status', ['ENABLED', 'DISABLED'])->default('ENABLED');
            $table->uuid('token')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
