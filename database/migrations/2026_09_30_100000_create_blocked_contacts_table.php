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
        // Addresses and domains that asked not to be mailed. Kept apart from the leads,
        // so deleting or re-scraping a lead never brings the address back.
        Schema::create('blocked_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('domain')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
            $table->unique(['user_id', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_contacts');
    }
};
