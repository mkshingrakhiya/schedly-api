<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('content');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();

            $table->index('workspace_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
