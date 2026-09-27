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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step');
            $table->string('subject');
            $table->text('body');
            $table->string('status');
            $table->string('thread_id')->nullable();
            // The RFC Message-ID header, so replies can be matched by In-Reply-To.
            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            // When the sender may hand it to SMTP; null once it is out.
            $table->timestamp('send_after')->nullable();
            // Why the last send attempt failed.
            $table->string('error')->nullable();
            $table->text('reply_body')->nullable();
            $table->timestamp('reply_received_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_after']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
