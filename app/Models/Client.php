<?php

namespace App\Models;

use App\Enums\ClientEntryType;
use App\Enums\RoadmapTemplate;
use App\Enums\WhoseMove;
use App\Exceptions\ClientCannotBeDeleted;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Client extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_followup_at' => 'datetime',
            'followup_has_time' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->last_activity_at ??= now();
        });
    }

    /** Clients are never deleted. Move them to a "lost" stage instead. */
    public function delete()
    {
        throw new ClientCannotBeDeleted;
    }

    /** Create a client in the first visible stage with an opening entry. */
    public static function start(array $attributes, ?User $by = null): self
    {
        return DB::transaction(function () use ($attributes, $by) {
            $attributes['stage_id'] ??= PipelineStage::visible()->value('id');
            $attributes['owner_user_id'] ??= $by?->id;

            $client = static::create($attributes);
            $client->record(ClientEntryType::Note, 'Client added to Pulse.', by: $by, toStage: $client->stage_id);

            return $client;
        });
    }

    // Relations ---------------------------------------------------------

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ClientEntry::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function latestEntry(): HasOne
    {
        // The last real conversation, not funnel bookkeeping.
        return $this->hasOne(ClientEntry::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn ($q) => $q->whereNotIn('type', [ClientEntryType::StageChange->value, ClientEntryType::FollowUp->value]),
        );
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // Scopes ------------------------------------------------------------

    /** Follow-up date is today or earlier, on clients still in play. */
    public function scopeDue(Builder $query): void
    {
        $query->whereNotNull('next_followup_at')
            ->whereDate('next_followup_at', '<=', today()->toDateString())
            ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false));
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotNull('next_followup_at')
            ->whereDate('next_followup_at', '<', today()->toDateString())
            ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false));
    }

    // State -------------------------------------------------------------

    public function isOverdue(): bool
    {
        return $this->next_followup_at !== null && $this->next_followup_at->lt(today());
    }

    public function isDueToday(): bool
    {
        return $this->next_followup_at?->isSameDay(today()) ?? false;
    }

    /** "Fri, 7 Nov" or "Fri, 7 Nov, 3:00 PM"; empty when nothing is scheduled. */
    public function followUpLabel(): string
    {
        if (! $this->next_followup_at) {
            return '';
        }

        return $this->next_followup_at->format($this->followup_has_time ? 'D, j M, g:i A' : 'D, j M');
    }

    // Actions -----------------------------------------------------------

    /**
     * Log a conversation. A next follow-up date is required, or an explicit "no follow-up needed".
     */
    public function logUpdate(
        ClientEntryType $type,
        string $note,
        ?CarbonInterface $nextFollowUp,
        bool $hasTime = false,
        bool $noFollowUp = false,
        ?User $by = null,
    ): ClientEntry {
        if (trim($note) === '') {
            throw new InvalidArgumentException('Write what was discussed.');
        }

        if ($nextFollowUp === null && ! $noFollowUp) {
            throw new InvalidArgumentException('Set the next follow-up date, or mark that no follow-up is needed.');
        }

        return DB::transaction(function () use ($type, $note, $nextFollowUp, $hasTime, $by) {
            $this->forceFill([
                'next_followup_at' => $nextFollowUp,
                'followup_has_time' => $nextFollowUp !== null && $hasTime,
            ]);

            return $this->record($type, $note, $nextFollowUp, $by);
        });
    }

    public function setFollowUp(?CarbonInterface $at, bool $hasTime = false, ?User $by = null): ?ClientEntry
    {
        if ($at === null && $this->next_followup_at === null) {
            return null;
        }

        $this->forceFill(['next_followup_at' => $at, 'followup_has_time' => $at !== null && $hasTime]);

        return $this->record(
            ClientEntryType::FollowUp,
            $at ? 'Next follow-up: '.($hasTime ? $at->format('D, j M, g:i A') : $at->format('D, j M')).'.' : 'Follow-up cleared.',
            $at,
            $by,
        );
    }

    public function changeStage(PipelineStage $to, ?User $by = null, ?string $note = null): ?ClientEntry
    {
        if ($this->stage_id === $to->id) {
            return null;
        }

        $from = $this->stage_id;
        $fromName = $this->stage->name;
        $this->forceFill(['stage_id' => $to->id]);

        return $this->record(
            ClientEntryType::StageChange,
            $note ?: "Moved from {$fromName} to {$to->name}.",
            by: $by,
            fromStage: $from,
            toStage: $to->id,
        );
    }

    /** The conversion: start a project under this client's name and move them to the won stage. */
    public function startProject(string $name, ?User $by = null, RoadmapTemplate $template = RoadmapTemplate::Standard): Project
    {
        return DB::transaction(function () use ($name, $by, $template) {
            $project = Project::start([
                'name' => trim($name),
                'client_name' => $this->name,
                'client_id' => $this->id,
                'whose_move' => WhoseMove::Us,
            ], $template, $by);

            $won = PipelineStage::visible()->where('is_won', true)->first();

            if ($won && $this->stage_id !== $won->id) {
                $this->changeStage($won, $by, "Project started: {$project->name}.");
            } else {
                $this->record(ClientEntryType::Note, "Project started: {$project->name}.", by: $by);
            }

            return $project;
        });
    }

    /** Append an entry and persist pending client changes. */
    protected function record(
        ClientEntryType $type,
        ?string $note,
        ?CarbonInterface $nextFollowUp = null,
        ?User $by = null,
        ?int $fromStage = null,
        ?int $toStage = null,
    ): ClientEntry {
        return DB::transaction(function () use ($type, $note, $nextFollowUp, $by, $fromStage, $toStage) {
            $at = now();

            $entry = $this->entries()->create([
                'user_id' => ($by ?? auth()->user())?->id,
                'type' => $type,
                'note' => filled($note) ? trim($note) : null,
                'from_stage_id' => $fromStage,
                'to_stage_id' => $toStage,
                'next_followup_at' => $nextFollowUp,
                'created_at' => $at,
            ]);

            $this->forceFill(['last_activity_at' => $at])->save();
            $this->unsetRelation('stage');

            return $entry;
        });
    }
}
