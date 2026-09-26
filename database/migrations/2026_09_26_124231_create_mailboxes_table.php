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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('address');
            $table->string('type');
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('username');
            $table->text('password');
            $table->timestamp('connection_checked_at')->nullable();
            $table->string('connection_error')->nullable();
            $table->string('status');
            $table->unsignedSmallInteger('daily_limit');
            $table->unsignedSmallInteger('sent_today')->default(0);
            $table->date('sent_today_on')->nullable();
            $table->timestamp('warm_up_started_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'address']);
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
