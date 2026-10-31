<?php

namespace App\Exceptions;

use LogicException;

class HighPrioritySkipNotConfirmed extends LogicException
{
    public function __construct()
    {
        parent::__construct('Skipping a high priority item needs a double confirmation.');
    }
}
