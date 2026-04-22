<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('owner_id');
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
