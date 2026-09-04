<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle status of a CV document.
 */
enum CvStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
}
