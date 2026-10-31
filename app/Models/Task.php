<?php

namespace App\Models;

use App\Enums\EntryType;
use App\Enums\Recurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime', 'is_high' => 'boolean', 'recurrence' => Recurrence::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function skips(): HasMany
    {
        return $this->hasMany(DailySkip::class);
    }

    /** Owners see all tasks; others see tasks on their projects and tasks they created. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isOwner()) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('tasks.user_id', $user->id)
            ->orWhereIn('tasks.project_id', Project::visibleTo($user)->select('projects.id')));
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /** On today's list: open, and due today or earlier (or with no date). Later ones wait for their day. */
    public function scopeForToday(Builder $query): void
    {
        $query->open()->where(fn (Builder $q) => $q->whereNull('due_date')->orWhereDate('due_date', '<=', today()->toDateString()));
    }

    /** Needs today's check-in: on today's list and not skipped today. Completing it is the other way to touch it. */
    public function scopeUntouchedToday(Builder $query): void
    {
        $query->forToday()->whereNotIn('tasks.id', DailySkip::whereDate('skipped_on', today()->toDateString())->whereNotNull('task_id')->select('task_id'));
    }

    public function scopeDone(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_date?->lt(today());
    }

    /** Completing a task linked to an active project logs a note on its timeline. */
    public function complete(): void
    {
        if ($this->isDone()) {
            return;
        }

        DB::transaction(function () {
            $this->forceFill(['completed_at' => now()])->save();

            // A recurring task comes back as a fresh open task on its next day.
            if ($this->recurrence) {
                static::create([
                    'title' => $this->title, 'project_id' => $this->project_id, 'user_id' => $this->user_id,
                    'is_high' => $this->is_high, 'recurrence' => $this->recurrence,
                    'due_date' => $this->recurrence->nextAfterToday($this->due_date),
                ]);
            }

            $project = $this->project;

            if ($project && ! $project->isArchived()) {
                $project->postEntry(EntryType::Note, 'Task done', 'Completed task – '.$this->title);
            }
        });
    }

    public function reopen(): void
    {
        $this->forceFill(['completed_at' => null])->save();
    }

    /** Nothing to do on it today: hidden until tomorrow. A recurring task also moves to its next day. */
    public function skipToday(User $user): DailySkip
    {
        if ($this->isDone()) {
            throw new \InvalidArgumentException('Finished tasks cannot be skipped.');
        }

        return DB::transaction(function () use ($user) {
            $skip = DailySkip::firstOrCreate(
                ['task_id' => $this->id, 'skipped_on' => today()->toDateString()],
                ['user_id' => $user->id, 'was_high' => (bool) $this->is_high],
            );

            if ($this->recurrence && $skip->wasRecentlyCreated) {
                $this->forceFill(['due_date' => $this->recurrence->nextAfterToday($this->due_date)])->save();
            }

            return $skip;
        });
    }
}
