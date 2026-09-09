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
        Schema::table('code', function (Blueprint $table) {
            $table->enum('transition_type', ['default', 'good', 'processing'])->default('default');
            $table->string('transition_create')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('code', function (Blueprint $table) {
            $table->dropColumn('transition_type', 'transition_create');
        });
    }
};
