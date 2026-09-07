<?php

declare(strict_types=1);

namespace App\Enum;

enum Format: string
{
    case Remote = 'REMOTE';
    case Local = 'ON_SITE';
    case Hybrid = 'HYBRID';

    public function label(): string
    {
        return match($this) {
            self::Remote => 'Remote',
            self::Local => 'On-site',
            self::Hybrid => 'Hybrid',
        };
    }
}