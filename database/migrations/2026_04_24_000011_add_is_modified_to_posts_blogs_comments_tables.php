<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_modified')->default(false)->after('is_published');
        });

        Schema::table('blogs', function (Blueprint $table) {
            $table->boolean('is_modified')->default(false)->after('is_published');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('is_modified')->default(false)->after('is_highlighted');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('is_modified');
        });

        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn('is_modified');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('is_modified');
        });
    }
};
