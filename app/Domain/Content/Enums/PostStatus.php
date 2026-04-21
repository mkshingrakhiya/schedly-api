<?php

namespace App\Domain\Content\Enums;

enum PostStatus: string
{
    case Scheduled = 'scheduled';
    case PartiallyPublished = 'partially_published';
    case Published = 'published';
    case Failed = 'failed';
}
