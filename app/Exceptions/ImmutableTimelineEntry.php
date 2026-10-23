<?php

namespace App\Exceptions;

use LogicException;

class ImmutableTimelineEntry extends LogicException
{
    public function __construct()
    {
        parent::__construct('Timeline entries are append-only and cannot be edited or deleted.');
    }
}
