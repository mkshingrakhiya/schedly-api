<?php

namespace App\Services\SocialPlatforms\Contracts;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
use App\Services\SocialPlatforms\Data\PlatformPublishResponse;

interface SocialPlatformPublisher
{
    public function publish(Post $post, PostTarget $target): PlatformPublishResponse;
}
