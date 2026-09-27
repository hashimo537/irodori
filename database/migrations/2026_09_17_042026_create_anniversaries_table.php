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
        Schema::create('anniversaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            // だれの記念日か。null なら家族の記念日（結婚記念日など）
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');                          // 「ゆいの誕生日」
            $table->unsignedTinyInteger('month');             // 1〜12
            $table->unsignedTinyInteger('day');               // 1〜31
            $table->unsignedSmallInteger('start_year')->nullable();  // 生まれた年。入れると「7さい」が出る
            $table->timestamps();

            $table->index(['family_id', 'month', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anniversaries');
    }
};
