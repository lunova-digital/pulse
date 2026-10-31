<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PipelineStage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_won' => 'boolean', 'is_lost' => 'boolean', 'is_hidden' => 'boolean'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'stage_id');
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_hidden', false)->orderBy('position')->orderBy('id');
    }

    /** Stages are never deleted: they are hidden, and only when no client sits in them. */
    public function delete()
    {
        throw new LogicException('Pipeline stages are hidden, not deleted.');
    }

    public function hide(): void
    {
        if ($this->clients()->exists()) {
            throw new LogicException('Move the clients out of this stage before hiding it.');
        }

        if ($this->is_won && ! static::visible()->where('is_won', true)->whereKeyNot($this->id)->exists()) {
            throw new LogicException('Keep at least one visible "won" stage.');
        }

        $this->update(['is_hidden' => true]);
    }
}
