<?php

namespace App\Domain\Content\Enums;

enum PostTargetPublishAttemptStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
