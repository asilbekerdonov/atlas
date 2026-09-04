<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when a category name sent by the client does not match any row in
 * the attribute_category lookup table. Maps to HTTP 422.
 */
final class CategoryNotFoundException extends \RuntimeException
{
    public function __construct(string $categoryName)
    {
        parent::__construct(sprintf('Category "%s" not found.', $categoryName));
    }
}
