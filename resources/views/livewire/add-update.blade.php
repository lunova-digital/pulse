<div>
    @if ($open)
        <x-modal variant="panel" title="Add update">
            <div class="flex flex-1 flex-col gap-4 overflow-auto p-6">
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Project</div>
                    @if ($fixed)
                        <div class="flex h-11 items-center rounded-lg bg-hover px-4 font-semibold">{{ $projectName }}</div>
                    @else
                        <select wire:model.live="form.projectId" class="field">
                            <option value="">Choose a project</option>
                            @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                        @error('form.projectId')<div class="text-red">{{ $message }}</div>@enderror
                    @endif
                </div>
                <x-update-fields :labels="$labels" :rows="4" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="close" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="post" x-data
                    x-bind:disabled="!$wire.form.projectId || !($wire.form.label.trim() || $wire.form.note.trim())"
                    class="btn-primary">Post update</button>
            </div>
        </x-modal>
    @endif
</div>
