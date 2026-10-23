<?php

namespace App\Enums;

enum RoadmapTemplate: string
{
    case Standard = 'standard';
    case Content = 'content';
    case Simple = 'simple';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Web / software',
            self::Content => 'Content / channel',
            self::Simple => 'Simple',
        };
    }

    /** @return list<string> */
    public function steps(): array
    {
        return match ($this) {
            self::Standard => ['SRS', 'Design', 'Development', 'Testing', 'Delivery', 'Maintenance'],
            self::Content => ['Planning', 'Scripting', 'Production', 'Review', 'Publishing'],
            self::Simple => ['Kickoff', 'Build', 'Delivery'],
        };
    }
}
