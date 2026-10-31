<div class="mx-auto flex max-w-[800px] flex-col gap-6" style="animation: pFade 200ms">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="h1 flex-1">Team</h1>
        <button type="button" wire:click="openNew" class="btn-primary"><x-icon name="plus" />Add person</button>
    </div>

    @if ($credentials)
        <div class="card flex flex-col gap-2 border-accent p-4">
            <div class="font-semibold">Share these sign-in details now. The password is not shown again.</div>
            <div>Email: <span class="font-semibold">{{ $credentials['email'] }}</span></div>
            <div>Password: <span class="font-mono font-semibold select-all">{{ $credentials['password'] }}</span></div>
            <button type="button" wire:click="dismissCredentials" class="btn-secondary self-start">I have copied it</button>
        </div>
    @endif

    <div class="card overflow-hidden">
        @foreach ($users as $user)
            <div wire:key="user-{{ $user->id }}" class="-mt-px flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 border-t border-border py-2 pr-2 pl-4 first:border-t-0">
                <div class="min-w-0 flex-[1_1_200px]">
                    <div class="truncate font-semibold">{{ $user->name }}</div>
                    <div class="truncate text-text2">{{ $user->email }}</div>
                </div>
                <span class="tag-gray">{{ $user->role->label() }}</span>
                @if ($user->isOwner())
                    <span class="text-text2">Everything</span>
                @else
                    <span class="text-text2">{{ $user->projects_count }} {{ \Illuminate\Support\Str::plural('item', $user->projects_count) }}</span>
                    @if ($user->can_manage_clients)<span class="tag-gray">Clients</span>@endif
                    @unless ($user->is_active)<span class="tag-red">Deactivated</span>@endunless
                    <button type="button" wire:click="openEdit({{ $user->id }})" class="btn-ghost">Edit</button>
                @endif
            </div>
        @endforeach
    </div>

    @if ($editing !== null)
        <x-modal :title="$editing === 'new' ? 'Add person' : 'Edit person'" close="close">
            <div class="flex flex-1 flex-col gap-4 overflow-auto p-6">
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Name</span>
                    <input wire:model="name" class="field" autofocus>
                    @error('name')<span class="text-red">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Email</span>
                    <input type="email" wire:model="email" class="field">
                    @error('email')<span class="text-red">{{ $message }}</span>@enderror
                </label>
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Access level</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([\App\Enums\Role::Member, \App\Enums\Role::Client] as $r)
                            <button type="button" wire:click="$set('role', '{{ $r->value }}')" @class(['chip', 'chip-on' => $role === $r->value, 'chip-off' => $role !== $r->value])>{{ $r->label() }}</button>
                        @endforeach
                    </div>
                    <div class="text-text2">{{ $role === 'client' ? 'Sees only the items shared below, read-only, without internal notes.' : 'Can view, edit and post updates on the items shared below.' }}</div>
                </div>
                @if ($role === 'member')
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="clients" class="size-4 accent-accent"><span>Can use the Clients tab</span></label>
                @endif
                @if ($editing !== 'new')
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="active" class="size-4 accent-accent"><span>Active (untick to block sign-in; history is kept)</span></label>
                @endif
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Projects and products they can open</div>
                    <div class="flex max-h-64 flex-col overflow-auto rounded-lg border border-border">
                        @foreach ($projects as $p)
                            <label wire:key="pick-{{ $p->id }}" class="flex min-h-11 items-center gap-2 border-t border-border px-4 first:border-t-0">
                                <input type="checkbox" wire:model="projectIds" value="{{ $p->id }}" class="size-4 accent-accent">
                                <span class="min-w-0 flex-1 truncate">{{ $p->name }}</span>
                                <span class="tag-gray">{{ $p->kind->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('projectIds')<span class="text-red">{{ $message }}</span>@enderror
                </div>
                @if ($editing !== 'new')
                    <button type="button" wire:click="resetPassword" wire:confirm="Generate a new password for this person?" class="btn-secondary self-start">Reset password</button>
                @endif
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="close" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="save" class="btn-primary">{{ $editing === 'new' ? 'Create' : 'Save' }}</button>
            </div>
        </x-modal>
    @endif
</div>
