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
        Schema::create('brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand_name', 255);
            $table->text('description')->nullable();
            $table->string('industry', 120)->nullable();
            $table->string('target_audience', 500)->nullable();
            $table->string('preferred_tone', 100)->nullable();
            $table->json('preferred_languages');
            $table->text('content_guidelines')->nullable();
            $table->text('avoid_terms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_profiles');
    }
};
