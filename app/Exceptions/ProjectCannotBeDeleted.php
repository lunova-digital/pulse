<?php

namespace App\Exceptions;

use LogicException;

class ProjectCannotBeDeleted extends LogicException
{
    public function __construct()
    {
        parent::__construct('Projects are never deleted. Archive the project instead.');
    }
}
