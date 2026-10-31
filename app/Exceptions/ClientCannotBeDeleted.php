<?php

namespace App\Exceptions;

use LogicException;

class ClientCannotBeDeleted extends LogicException
{
    public function __construct()
    {
        parent::__construct('Clients are never deleted. Move them to the Lost stage instead.');
    }
}
