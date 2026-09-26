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
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();
            $table->string('type');
            $table->string('status');
            $table->unsignedSmallInteger('daily_limit');
            $table->unsignedSmallInteger('sent_today')->default(0);
            $table->date('sent_today_on')->nullable();
            $table->timestamp('warm_up_started_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
