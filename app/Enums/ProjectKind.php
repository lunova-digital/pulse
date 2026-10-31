<?php

namespace App\Enums;

enum ProjectKind: string
{
    case Project = 'project';
    case Product = 'product';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function plural(): string
    {
        return $this->label().'s';
    }

    public function route(): string
    {
        return $this === self::Product ? 'products.index' : 'projects.index';
    }
}
