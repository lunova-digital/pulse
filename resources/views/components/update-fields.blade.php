{{-- Shared fields for the Add Update box and panel. Expects a Livewire `form` (UpdateForm) on the host component. --}}
@props(['labels' => [], 'rows' => 3])
@php
    $types = \App\Enums\EntryType::postable();
    $moves = \App\Enums\WhoseMove::cases();
    $form = $this->form;
@endphp
<div class="flex flex-col gap-2">
    <div class="font-semibold">Event type</div>
    <div class="flex flex-wrap gap-2">
        @foreach ($types as $t)
            <button type="button" wire:click="$set('form.type', '{{ $t->value }}')" @class(['chip pl-2', 'chip-on' => $form->type === $t->value, 'chip-off' => $form->type !== $t->value])>
                <x-icon :name="$t->icon()" />{{ $t->label() }}
            </button>
        @endforeach
    </div>
</div>
<label class="flex flex-col gap-2">
    <span class="font-semibold">Status label</span>
    <input list="pulse-labels-{{ $this->getId() }}" wire:model="form.label" placeholder="e.g. Design v2 sent" class="field">
</label>
<label class="flex flex-col gap-2">
    <span class="font-semibold">Note</span>
    <textarea wire:model="form.note" rows="{{ $rows }}" placeholder="What happened, and what's next?" class="textarea {{ $rows > 3 ? 'min-h-28' : 'min-h-24' }}"></textarea>
</label>
<div class="flex flex-col gap-2">
    <div class="font-semibold">Whose move?</div>
    <div class="flex flex-wrap gap-2">
        @foreach ($moves as $m)
            <button type="button" wire:click="$set('form.move', '{{ $m->value }}')" @class(['chip', $m->chipOn() => $form->move === $m->value, 'chip-off' => $form->move !== $m->value])>
                <span class="size-1.5 rounded-full {{ $m->dot() }}"></span>{{ $m->label() }}
            </button>
        @endforeach
    </div>
</div>
<label class="flex items-center gap-2 text-text2">
    <input type="checkbox" wire:model="form.internal" class="size-4 accent-accent">
    <span>Internal only (hidden from client-portal users)</span>
</label>
@error('form.label')<div class="text-red">{{ $message }}</div>@enderror
<datalist id="pulse-labels-{{ $this->getId() }}">
    @foreach ($labels as $l)<option value="{{ $l }}"></option>@endforeach
</datalist>
