<?php

namespace App\Enums;

enum WhoseMove: string
{
    case Us = 'us';
    case Client = 'client';
    case None = 'none';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Us => 'Waiting on us',
            self::Client => 'Waiting on client',
            self::None => 'No action',
            self::Blocked => 'Blocked',
        };
    }

    /** Tailwind classes for the tinted pill (background + text). */
    public function pill(): string
    {
        return match ($this) {
            self::Us => 'bg-orange-t text-orange',
            self::Client => 'bg-blue-t text-blue',
            self::None => 'bg-gray-t text-text2',
            self::Blocked => 'bg-red-t text-red',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Us => 'bg-orange',
            self::Client => 'bg-blue',
            self::None => 'bg-gray',
            self::Blocked => 'bg-red',
        };
    }

    /** Classes for a selected filter/option chip. */
    public function chipOn(): string
    {
        return match ($this) {
            self::Us => 'border-orange bg-orange-t text-orange',
            self::Client => 'border-blue bg-blue-t text-blue',
            self::None => 'border-gray bg-gray-t text-text2',
            self::Blocked => 'border-red bg-red-t text-red',
        };
    }
}
