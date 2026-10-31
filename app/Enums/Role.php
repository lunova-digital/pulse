<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Member = 'member';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Member => 'Team member',
            self::Client => 'Client (read-only)',
        };
    }
}
