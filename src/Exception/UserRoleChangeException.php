<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when an admin tries to promote/demote a user whose role cannot be
 * changed or when removing the last administrator.
 * Maps to a flash error in the admin panel.
 */
final class UserRoleChangeException extends \RuntimeException
{
}
