<?php

namespace App\Models;

use App\Enums\EntryType;
use App\Enums\MilestoneState;
use App\Enums\ProjectKind;
use App\Enums\RoadmapTemplate;
use App\Enums\WhoseMove;
use App\Exceptions\ProjectCannotBeDeleted;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Project extends Model
{
    use HasFactory;

    public const STALE_AFTER_DAYS = 5;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => ProjectKind::class,
            'whose_move' => WhoseMove::class,
            'whose_move_since' => 'datetime',
            'next_meeting_at' => 'datetime',
            'is_pinned' => 'boolean',
            'archived_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->kind ??= ProjectKind::Project;
            $project->whose_move_since ??= now();
            $project->last_activity_at ??= now();
        });

        // Every new project starts with the default roadmap (first milestone current).
        static::created(function (Project $project) {
            if (! $project->milestones()->exists()) {
                $project->replaceRoadmap(RoadmapTemplate::Standard->steps(), 0);
            }
        });
    }

    /** Projects are never deleted. Archive them instead. */
    public function delete()
    {
        throw new ProjectCannotBeDeleted;
    }

    /**
     * Create a project with its roadmap template and an opening timeline entry.
     *
     * @param  array{name: string, client_name?: ?string, description?: ?string, whose_move?: WhoseMove, next_meeting_at?: mixed}  $attributes
     */
    public static function start(array $attributes, RoadmapTemplate $template = RoadmapTemplate::Standard, ?User $creator = null): self
    {
        return DB::transaction(function () use ($attributes, $template, $creator) {
            $project = static::create($attributes);

            // Whoever creates an item can always see it (owners see everything anyway).
            if ($creator && ! $creator->isOwner()) {
                $project->users()->attach($creator->id);
            }

            if ($template !== RoadmapTemplate::Standard) {
                $project->replaceRoadmap($template->steps(), 0);
            }

            $project->recordEntry(
                EntryType::Note,
                'Project created',
                $project->description ?: 'Project added to Pulse.',
                $project->whose_move,
            );

            return $project;
        });
    }

    // Relations ---------------------------------------------------------

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimelineEntry::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function latestEntry(): HasOne
    {
        return $this->hasOne(TimelineEntry::class)->ofMany(['created_at' => 'max', 'id' => 'max']);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function skips(): HasMany
    {
        return $this->hasMany(DailySkip::class);
    }

    // Scopes ------------------------------------------------------------

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /** Items a user may see: owners see all, everyone else only what is assigned to them. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isOwner()) {
            return;
        }

        $query->whereHas('users', fn (Builder $q) => $q->where('users.id', $user->id));
    }

    public function scopeOfKind(Builder $query, ProjectKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /** Needs a daily check-in: no update posted today and not skipped today. */
    public function scopeUntouchedToday(Builder $query): void
    {
        $query->active()
            ->whereNotIn('id', TimelineEntry::where('created_at', '>=', today())->select('project_id'))
            ->whereNotIn('id', DailySkip::whereDate('skipped_on', today()->toDateString())->whereNotNull('project_id')->select('project_id'));
    }

    /** No activity for 5+ calendar days. */
    public function scopeStale(Builder $query): void
    {
        $query->where('last_activity_at', '<', now()->startOfDay()->subDays(self::STALE_AFTER_DAYS - 1));
    }

    public function scopeWaitingOnUs(Builder $query): void
    {
        $query->where('whose_move', WhoseMove::Us);
    }

    public function scopeWaitingOnClient(Builder $query): void
    {
        $query->where('whose_move', WhoseMove::Client);
    }

    /** Meeting today or within the next 3 days. */
    public function scopeMeetingsSoon(Builder $query): void
    {
        $query->where('next_meeting_at', '>=', now()->startOfDay())
            ->where('next_meeting_at', '<', now()->startOfDay()->addDays(4));
    }

    // State -------------------------------------------------------------

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isStale(): bool
    {
        return ! $this->isArchived()
            && $this->daysSince($this->last_activity_at) >= self::STALE_AFTER_DAYS;
    }

    /** Next meeting date has passed and no meeting entry was posted since. */
    public function meetingNotLogged(): bool
    {
        if ($this->isArchived() || ! $this->next_meeting_at || $this->next_meeting_at->isFuture()) {
            return false;
        }

        return ! $this->entries()
            ->where('type', EntryType::Meeting)
            ->where('created_at', '>=', $this->next_meeting_at->copy()->startOfDay())
            ->exists();
    }

    public function daysSince(?CarbonInterface $date): int
    {
        return $date ? (int) $date->copy()->startOfDay()->diffInDays(now()->startOfDay(), false) : 0;
    }

    /** "Waiting on us · 2d", "No action" or "Archived". */
    public function moveBadge(): string
    {
        return match (true) {
            $this->isArchived() => 'Archived',
            $this->whose_move === WhoseMove::None => $this->whose_move->label(),
            default => $this->whose_move->label().' · '.$this->daysSince($this->whose_move_since).'d',
        };
    }

    /** "Label: note" of the most recent entry (needs `latestEntry` for no extra query). */
    public function statusLine(): string
    {
        $entry = $this->latestEntry;

        if (! $entry) {
            return '';
        }

        return $entry->note ? trim(($entry->status_label ?? $entry->type->label()).': '.$entry->note) : (string) $entry->status_label;
    }

    public function archiveReason(): ?string
    {
        return $this->entries()->where('type', EntryType::Archived)->value('note');
    }

    /** @return list<string> distinct status labels used on projects the user can see */
    public static function statusLabelSuggestions(?User $user = null): array
    {
        return TimelineEntry::query()
            ->when($user, fn ($q) => $q->whereIn('project_id', static::visibleTo($user)->select('projects.id')))
            ->whereNotNull('status_label')
            ->whereNotIn('type', [EntryType::Archived, EntryType::Reopened])
            ->distinct()
            ->orderBy('status_label')
            ->pluck('status_label')
            ->all();
    }

    // Actions -----------------------------------------------------------

    /**
     * Append a timeline entry, bump activity and (if given and different) change whose move.
     */
    public function postEntry(
        EntryType $type,
        ?string $statusLabel = null,
        ?string $note = null,
        ?WhoseMove $whoseMove = null,
        bool $internal = false,
    ): TimelineEntry {
        if ($this->isArchived()) {
            throw new InvalidArgumentException('Archived projects are read-only. Reopen the project first.');
        }

        return $this->recordEntry($type, $statusLabel, $note, $whoseMove, $internal);
    }

    // Daily priority ----------------------------------------------------

    /** Pinned = today's priority. The pin stays until it is changed, so yesterday's priority carries over. */
    public function togglePriority(): void
    {
        if ($this->isArchived()) {
            throw new InvalidArgumentException('Archived projects cannot be a priority.');
        }

        $this->forceFill(['is_pinned' => ! $this->is_pinned])->save();
    }

    public function isSkippedToday(): bool
    {
        return $this->skips()->whereDate('skipped_on', today()->toDateString())->exists();
    }

    /** Hide the project for today: nothing to work on, logged silently (not a timeline event). */
    public function skipToday(User $user): DailySkip
    {
        if ($this->isArchived()) {
            throw new InvalidArgumentException('Archived projects cannot be skipped.');
        }

        return DailySkip::firstOrCreate(
            ['project_id' => $this->id, 'skipped_on' => today()->toDateString()],
            ['user_id' => $user->id, 'was_high' => $this->is_pinned],
        );
    }

    public function changeWhoseMove(WhoseMove $to): ?TimelineEntry
    {
        $from = $this->whose_move;

        if ($from === $to) {
            return null;
        }

        return $this->postEntry(
            EntryType::Note,
            $to->label(),
            "Whose move changed from {$from->label()} to {$to->label()}.",
            $to,
        );
    }

    public function scheduleMeeting(?CarbonInterface $at): ?TimelineEntry
    {
        $current = $this->next_meeting_at;

        if (($at === null && $current === null) || ($at !== null && $current !== null && $at->equalTo($current))) {
            return null;
        }

        $this->forceFill(['next_meeting_at' => $at])->save();

        return $this->postEntry(
            EntryType::Note,
            $at ? 'Meeting scheduled' : 'Meeting cleared',
            $at ? 'Next meeting: '.$at->format('D, j M, g:i A').'.' : 'Next meeting removed.',
        );
    }

    public function archive(string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('An archive reason is required.');
        }

        if ($this->isArchived()) {
            throw new InvalidArgumentException('Project is already archived.');
        }

        DB::transaction(function () use ($reason) {
            $entry = $this->recordEntry(EntryType::Archived, 'Archived', $reason);
            $this->forceFill(['archived_at' => $entry->created_at, 'is_pinned' => false])->save();
        });
    }

    public function reopen(string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reopen reason is required.');
        }

        if (! $this->isArchived()) {
            throw new InvalidArgumentException('Project is not archived.');
        }

        DB::transaction(function () use ($reason) {
            $this->forceFill(['archived_at' => null])->save();
            // A reopened project is ours to pick up.
            $this->recordEntry(EntryType::Reopened, 'Reopened', $reason, WhoseMove::Us);
        });
    }

    /**
     * Replace the roadmap. Milestones before $current are done, after are upcoming.
     *
     * @param  list<string>  $titles
     */
    public function replaceRoadmap(array $titles, int $current): void
    {
        $titles = array_values(array_filter(array_map('trim', $titles), fn ($t) => $t !== ''));

        if ($titles === []) {
            throw new InvalidArgumentException('A roadmap needs at least one milestone.');
        }

        $current = max(0, min($current, count($titles) - 1));

        DB::transaction(function () use ($titles, $current) {
            $this->milestones()->delete();

            foreach ($titles as $i => $title) {
                $this->milestones()->create([
                    'title' => $title,
                    'position' => $i,
                    'state' => match (true) {
                        $i < $current => MilestoneState::Done,
                        $i === $current => MilestoneState::Current,
                        default => MilestoneState::Upcoming,
                    },
                ]);
            }
        });
    }

    /** Save an edited roadmap; logs a timeline entry when the current milestone changes. */
    public function saveRoadmap(array $titles, int $current): void
    {
        $before = $this->milestones()->where('state', MilestoneState::Current)->value('title');

        $this->replaceRoadmap($titles, $current);

        $after = $this->milestones()->where('state', MilestoneState::Current)->value('title');

        if ($after !== $before) {
            $this->postEntry(EntryType::Note, "Roadmap: {$after}", "Moved to {$after}.");
        }
    }

    protected function recordEntry(
        EntryType $type,
        ?string $statusLabel,
        ?string $note,
        ?WhoseMove $whoseMove = null,
        bool $internal = false,
    ): TimelineEntry {
        return DB::transaction(function () use ($type, $statusLabel, $note, $whoseMove, $internal) {
            $at = now();

            $entry = $this->entries()->create([
                'user_id' => auth()->id(),
                'visible_to_client' => ! $internal,
                'type' => $type,
                'status_label' => filled($statusLabel) ? trim($statusLabel) : null,
                'note' => filled($note) ? trim($note) : null,
                'whose_move' => $whoseMove,
                'created_at' => $at,
            ]);

            $changes = ['last_activity_at' => $at];

            if ($whoseMove !== null && $whoseMove !== $this->whose_move) {
                $changes['whose_move'] = $whoseMove;
                $changes['whose_move_since'] = $at;
            }

            $this->forceFill($changes)->save();

            return $entry;
        });
    }
}
