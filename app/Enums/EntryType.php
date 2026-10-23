<?php

namespace App\Enums;

enum EntryType: string
{
    case Call = 'call';
    case Meeting = 'meeting';
    case Development = 'development';
    case Delivery = 'delivery';
    case ClientFeedback = 'client_feedback';
    case Note = 'note';
    case Archived = 'archived';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::ClientFeedback => 'Client feedback',
            default => ucfirst($this->value),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'phone',
            self::Meeting => 'users',
            self::Development => 'code',
            self::Delivery => 'package-check',
            self::ClientFeedback => 'message',
            self::Note => 'sticky',
            self::Archived => 'archive',
            self::Reopened => 'rotate',
        };
    }

    /** Types a person can pick when posting an update. */
    public static function postable(): array
    {
        return [self::Call, self::Meeting, self::Development, self::Delivery, self::ClientFeedback, self::Note];
    }
}
