<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_oauth_connection_states', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('workspace_id');
            $table->index('user_id');
            $table->index('platform_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_oauth_connection_states');
    }
};
