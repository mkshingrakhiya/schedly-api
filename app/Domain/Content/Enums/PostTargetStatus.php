<?php

namespace App\Domain\Content\Enums;

enum PostTargetStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
