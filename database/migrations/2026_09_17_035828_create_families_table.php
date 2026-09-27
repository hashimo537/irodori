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
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // 「〇〇家」
            $table->string('invite_code', 8)->unique();     // 「A3F9K2QP」など最大８文字重複なし
            $table->string('location_name')->nullable();          // 「横浜市」
            $table->decimal('latitude', 8, 5)->nullable();        // 35.44780
            $table->decimal('longitude', 8, 5)->nullable();       // 139.64250
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
