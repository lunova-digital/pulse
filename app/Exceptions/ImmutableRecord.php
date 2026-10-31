<?php

namespace App\Exceptions;

use LogicException;

class ImmutableRecord extends LogicException
{
    public function __construct(string $what = 'This record')
    {
        parent::__construct("{$what} is append-only and cannot be edited or deleted.");
    }
}
