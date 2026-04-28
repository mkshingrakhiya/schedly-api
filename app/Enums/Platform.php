<?php

namespace App\Enums;

enum Platform: string
{
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(static fn (self $platform): string => $platform->value, self::cases());
    }

    public function slug(): string
    {
        return $this->value;
    }
}
