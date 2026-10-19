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
        Schema::create('publication_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('remote_post_id', 255)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('failure_type', 32)->nullable();
            $table->text('safe_error')->nullable();
            $table->timestamps();

            $table->unique(['scheduled_post_id', 'social_account_id']);
            $table->index(['status', 'retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_targets');
    }
};
