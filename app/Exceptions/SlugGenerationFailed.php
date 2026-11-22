<?php

namespace App\Exceptions;

use Exception;

class SlugGenerationFailed extends Exception
{
    public function __construct(
        public readonly string $name,
        public readonly int $attempts = 10,
    ) {
        parent::__construct("A unique slug could not be generated for the space \"{$name}\" after {$attempts} attempts.");
    }
}
