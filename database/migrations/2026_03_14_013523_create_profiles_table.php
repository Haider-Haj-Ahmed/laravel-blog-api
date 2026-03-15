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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->string('location')->nullable();
            $table->json('social_links')->nullable(); // Store as JSON: {"github": "url", "twitter": "url"}
            $table->string('cover_image')->nullable();
            $table->json('settings')->nullable(); // User preferences
            $table->integer('ranking_points')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('user_id'); // One profile per user
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
