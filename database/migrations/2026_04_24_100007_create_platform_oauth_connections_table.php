<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_oauth_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('provider_user_id');
            $table->text('access_token');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'platform_id', 'provider_user_id'], 'platform_oauth_connections_workspace_platform_provider_unique');
            $table->index('platform_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_oauth_connections');
    }
};
