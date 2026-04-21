<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('platform_account_id');
            $table->string('handle');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('workspace_id');
            $table->index('platform_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
