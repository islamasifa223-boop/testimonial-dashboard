<?php

namespace App\Exceptions;

use Exception;

class PlanLimitExceeded extends Exception
{
    public function __construct(
        public readonly string $resource,
        public readonly int $limit,
    ) {
        parent::__construct("The {$resource} limit of {$limit} has been reached for the current plan.");
    }
}
