<?php

namespace App\Enums;

enum ClientEntryType: string
{
    case Note = 'note';
    case Call = 'call';
    case Meeting = 'meeting';
    case Email = 'email';
    case StageChange = 'stage_change';
    case FollowUp = 'followup';

    public function label(): string
    {
        return match ($this) {
            self::StageChange => 'Stage change',
            self::FollowUp => 'Follow-up',
            default => ucfirst($this->value),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'phone',
            self::Meeting => 'users',
            self::Email => 'message',
            self::StageChange => 'grid',
            self::FollowUp => 'cal',
            self::Note => 'sticky',
        };
    }

    /** Types a person can pick when logging a conversation. */
    public static function postable(): array
    {
        return [self::Call, self::Meeting, self::Email, self::Note];
    }
}
