<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // お迎え担当。決まっていないことも多いので nullable
            $table->foreignId('pickup_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('title');                          // 「スイミング」
            $table->string('place')->nullable();              // 「市民プール」
            $table->unsignedTinyInteger('day_of_week');       // 0=日 ... 6=土
            $table->time('start_time');
            $table->time('end_time');
            $table->date('starts_on');                        // 通い始めた日
            $table->date('ends_on')->nullable();              // 辞めたら入れる
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
