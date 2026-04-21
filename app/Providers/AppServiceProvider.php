<?php

namespace App\Providers;

use App\Domain\Content\Models\Post;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;
use App\Policies\PostPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
    }
}
