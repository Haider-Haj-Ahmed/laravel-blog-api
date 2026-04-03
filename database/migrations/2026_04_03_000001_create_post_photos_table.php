<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('posts')
            ->whereNotNull('photo')
            ->orderBy('id')
            ->chunkById(100, function ($posts) {
                $rows = [];
                $now = now();

                foreach ($posts as $post) {
                    $rows[] = [
                        'post_id' => $post->id,
                        'path' => 'post_photos/' . $post->photo,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('post_photos')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_photos');
    }
};
