<?php

namespace App\Enums;

enum MilestoneState: string
{
    case Done = 'done';
    case Current = 'current';
    case Upcoming = 'upcoming';
}
