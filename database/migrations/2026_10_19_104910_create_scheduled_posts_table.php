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
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->text('content');
            $table->string('media_disk', 64)->nullable();
            $table->string('media_path', 1024)->nullable();
            $table->string('media_mime_type', 128)->nullable();
            $table->string('media_file_name', 255)->nullable();
            $table->timestamp('scheduled_at');
            $table->string('timezone', 64);
            $table->string('status', 32)->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->text('safe_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
    }
};
