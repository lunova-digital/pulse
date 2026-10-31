<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Owner-only: add people and choose exactly which projects and products they can open. */
class Team extends Component
{
    /** null = closed, 'new', or the id of the user being edited. */
    public int|string|null $editing = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'member';

    public bool $clients = false;

    public bool $active = true;

    /** @var list<int|string> */
    public array $projectIds = [];

    /** Shown once after creating a user or resetting a password. */
    public ?array $credentials = null;

    protected function guard(): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);
    }

    public function mount(): void
    {
        $this->guard();
    }

    public function openNew(): void
    {
        $this->guard();
        $this->resetValidation();
        $this->reset('name', 'email', 'clients', 'projectIds');
        $this->role = 'member';
        $this->active = true;
        $this->editing = 'new';
    }

    public function openEdit(int $id): void
    {
        $this->guard();
        $user = User::findOrFail($id);
        abort_if($user->isOwner(), 403, 'The owner account is managed through the environment.');

        $this->resetValidation();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->clients = $user->can_manage_clients;
        $this->active = $user->is_active;
        $this->projectIds = $user->projects()->pluck('projects.id')->map(fn ($i) => (string) $i)->all();
        $this->editing = $id;
    }

    public function close(): void
    {
        $this->editing = null;
    }

    public function dismissCredentials(): void
    {
        $this->credentials = null;
    }

    public function save(): void
    {
        $this->guard();

        $user = is_int($this->editing) ? User::findOrFail($this->editing) : null;
        abort_if($user?->isOwner(), 403);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in([Role::Member->value, Role::Client->value])],
            'projectIds' => [$this->role === 'client' ? 'required' : 'array', 'array'],
            'projectIds.*' => ['integer', 'exists:projects,id'],
        ], ['projectIds.required' => 'Share at least one project with a client-portal user.']);

        $password = null;

        DB::transaction(function () use (&$user, &$password, $data) {
            $attributes = [
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'role' => Role::from($data['role']),
                'can_manage_clients' => $data['role'] === 'member' && $this->clients,
                'is_active' => $this->active,
            ];

            if ($user) {
                $user->update($attributes);
            } else {
                $password = Str::password(14, symbols: false);
                $user = User::create($attributes + ['password' => $password]);
            }

            $user->projects()->sync(array_map('intval', $data['projectIds']));
        });

        $this->editing = null;

        if ($password) {
            $this->credentials = ['email' => $user->email, 'password' => $password];
        }

        $this->dispatch('toast', message: 'Saved '.$user->name);
    }

    public function resetPassword(): void
    {
        $this->guard();
        $user = User::findOrFail($this->editing);
        abort_if($user->isOwner(), 403);

        $password = Str::password(14, symbols: false);
        $user->update(['password' => $password]);

        $this->editing = null;
        $this->credentials = ['email' => $user->email, 'password' => $password];
    }

    public function render()
    {
        $this->guard();

        return view('livewire.team', [
            'users' => User::withCount('projects')->orderByRaw("role = 'owner' desc")->orderBy('name')->get(),
            'projects' => $this->editing !== null
                ? Project::orderBy('kind')->orderBy('name')->get(['id', 'name', 'kind', 'archived_at'])
                : collect(),
        ])->layoutData(['active' => 'team', 'title' => 'Team']);
    }
}
