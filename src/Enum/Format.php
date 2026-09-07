<?php

declare(strict_types=1);

namespace App\Enum;

enum Format: string
{
    case Remote = 'REMOTE';
    case Local = 'ON_SITE';
    case Hybrid = 'HYBRID';

    /**
     * Translation key for the human-readable label (rendered via |trans).
     * Never output the raw enum value or a hardcoded label in the UI.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::Remote => 'position.format.remote',
            self::Local => 'position.format.on_site',
            self::Hybrid => 'position.format.hybrid',
        };
    }
}
