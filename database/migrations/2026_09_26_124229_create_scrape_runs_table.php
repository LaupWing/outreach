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
        Schema::create('scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niche_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->string('place');
            $table->string('status');
            $table->unsignedSmallInteger('requests')->default(0);
            $table->unsignedInteger('found')->default(0);
            $table->unsignedInteger('with_email')->default(0);
            $table->unsignedInteger('blocked')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_runs');
    }
};
