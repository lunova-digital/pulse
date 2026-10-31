<?php

namespace App\Models;

use App\Enums\EntryType;
use App\Enums\WhoseMove;
use App\Exceptions\ImmutableTimelineEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only: entries can be created but never edited or deleted. */
class TimelineEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => EntryType::class,
            'whose_move' => WhoseMove::class,
            'visible_to_client' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new ImmutableTimelineEntry);
        static::deleting(fn () => throw new ImmutableTimelineEntry);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
