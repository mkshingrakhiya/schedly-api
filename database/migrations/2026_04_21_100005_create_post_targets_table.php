<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_targets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('post_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status');
            $table->timestamp('scheduled_at');
            $table->timestamp('published_at')->nullable();
            $table->json('platform_options')->nullable();
            $table->timestamps();

            $table->index('post_id');
            $table->index('channel_id');
            $table->unique(['channel_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_targets');
    }
};
