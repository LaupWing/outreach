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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('niche_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scrape_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('city')->nullable();
            $table->string('status');
            $table->string('source');
            $table->text('hook')->nullable();
            $table->json('signals')->nullable();
            // Values for the {{tags}} in the offer's mails, filled by the AI or typed by hand.
            $table->json('facts')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('next_action_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
