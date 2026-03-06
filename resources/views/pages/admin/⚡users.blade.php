<?php

use App\Mail\NewUserWelcome;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User Management')] class extends Component {
    public string $name = '';
    public string $email = '';
    public bool $showCreateForm = false;
    public ?int $selectedUserId = null;

    public function mount(): void
    {
        abort_if(! Auth::user()->hasPermission('admin'), 403);
    }

    #[Computed]
    public function users()
    {
        return User::with('permissions')->orderBy('name')->get();
    }

    #[Computed]
    public function permissions()
    {
        return Permission::orderBy('label')->get();
    }

    public function selectUser(int $id): void
    {
        $this->selectedUserId = $this->selectedUserId === $id ? null : $id;
    }

    public function togglePermission(int $userId, int $permissionId): void
    {
        User::findOrFail($userId)->permissions()->toggle($permissionId);
        unset($this->users);
    }

    public function createUser(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $plainPassword = Str::password(16);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $plainPassword,
        ]);

        Mail::to($user)->send(new NewUserWelcome($user, $plainPassword));

        $this->reset('name', 'email', 'showCreateForm');
        $this->dispatch('user-created');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Users') }}</flux:heading>
        <flux:button wire:click="$set('showCreateForm', true)" variant="primary" icon="plus">
            {{ __('New User') }}
        </flux:button>
    </div>

    @if ($showCreateForm)
        <flux:card class="max-w-lg">
            <flux:heading size="lg" class="mb-4">{{ __('Create User') }}</flux:heading>

            <form wire:submit="createUser" class="space-y-4">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />
                <flux:input wire:model="email" :label="__('Email')" type="email" required />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Create & Send Password') }}</flux:button>
                    <flux:button wire:click="$set('showCreateForm', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <x-action-message on="user-created">
        {{ __('User created and welcome email sent.') }}
    </x-action-message>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Permissions') }}</flux:table.column>
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row>
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($user->permissions as $permission)
                                <flux:badge color="{{ $permission->name === 'admin' ? 'amber' : 'zinc' }}" size="sm">
                                    {{ $permission->label }}
                                </flux:badge>
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->created_at->toDateString() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button
                            size="sm"
                            variant="{{ $selectedUserId === $user->id ? 'primary' : 'ghost' }}"
                            wire:click="selectUser({{ $user->id }})"
                        >{{ __('Permissions') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>

                @if ($selectedUserId === $user->id && $this->permissions->isNotEmpty())
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex flex-wrap gap-4 py-2">
                                @foreach ($this->permissions as $permission)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <flux:checkbox
                                            wire:click="togglePermission({{ $user->id }}, {{ $permission->id }})"
                                            :checked="$user->permissions->contains('id', $permission->id)"
                                        />
                                        <span class="text-sm">{{ $permission->label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endif
            @endforeach
        </flux:table.rows>
    </flux:table>

</div>
