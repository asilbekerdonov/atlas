<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Roles assigned to users of the platform.
 */
enum UserRole: string
{
    case ROLE_CANDIDATE = 'ROLE_CANDIDATE';
    case ROLE_RECRUITER = 'ROLE_RECRUITER';
    case ROLE_ADMIN = 'ROLE_ADMIN';
}
