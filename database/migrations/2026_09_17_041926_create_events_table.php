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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            // member_id が null のときは「家族ぜんいん」の予定
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            // お迎え担当。決まっていないことも多いので nullable
            $table->foreignId('pickup_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('place')->nullable();
            $table->date('date');
            $table->time('start_time')->nullable();           // null なら終日
            $table->time('end_time')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['family_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
