<?php

namespace App\Models;

use App\Enums\EntryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime', 'is_high' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
}
