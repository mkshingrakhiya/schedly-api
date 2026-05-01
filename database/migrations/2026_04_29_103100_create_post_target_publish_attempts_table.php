<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_target_publish_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('post_target_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->string('job_uuid')->nullable();
            $table->timestamps();

            $table->unique(['post_target_id', 'attempt_number']);
            $table->index(['post_target_id', 'status']);
            $table->index('job_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_target_publish_attempts');
    }
};
