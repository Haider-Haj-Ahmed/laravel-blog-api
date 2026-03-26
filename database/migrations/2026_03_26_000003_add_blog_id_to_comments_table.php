<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('blog_id')->nullable()->after('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blog_id');
            $table->foreignId('post_id')->nullable(false)->change();
        });
    }
};
