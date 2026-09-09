<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Input for the minimal email registration form. */
final class RegisterRequestDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public string $password = '',

        public string $confirmPassword = '',
    ) {
    }
}
