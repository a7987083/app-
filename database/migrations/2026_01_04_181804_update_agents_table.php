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
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('ipad_price', 12, 2)->default(68.00);
            $table->decimal('ipad_good_price', 12, 2)->default(68.00);
            $table->decimal('ipad_processing_price', 12, 2)->default(68.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('ipad_price', 'ipad_good_price', 'ipad_processing_price');
        });
    }
};
