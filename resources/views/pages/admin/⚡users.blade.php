<?php

use App\Mail\NewUserWelcome;
use App\Models\Permission;
use App\Models\User;
use App\Rules\SshPublicKey;
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
    public array $sftpUsername = [];
    public array $sftpPublicKey = [];

    public function mount(): void
    {
        abort_if(! Auth::user()->hasPermission('admin'), 403);
    }

    #[Computed]
    public function users()
    {
        return User::with('permissions', 'sftpUsers')->orderBy('name')->get();
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

    public function createSftpUser(int $userId): void
    {
        if (! empty($this->sftpPublicKey[$userId])) {
            $this->sftpPublicKey[$userId] = SshPublicKey::normalize($this->sftpPublicKey[$userId]);
        }

        $this->validate([
            "sftpUsername.{$userId}"  => ['required', 'string', 'max:32', 'alpha_dash', 'unique:sftp_users,username'],
            "sftpPublicKey.{$userId}" => ['nullable', 'string', new SshPublicKey],
        ], [], [
            "sftpUsername.{$userId}"  => 'username',
            "sftpPublicKey.{$userId}" => 'public key',
        ]);

        $user = User::findOrFail($userId);

        abort_if($user->sftpUsers()->exists(), 422, 'SFTP account already exists.');

        $user->sftpUsers()->create([
            'username'   => $this->sftpUsername[$userId],
            'public_key' => $this->sftpPublicKey[$userId] ?? null ?: null,
        ]);

        unset($this->sftpUsername[$userId], $this->sftpPublicKey[$userId], $this->users);

        $this->dispatch("sftp-created-{$userId}");
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
        unset($this->users);
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
                        >{{ __('Manage') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>

                @if ($selectedUserId === $user->id)
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex flex-col gap-6 py-3 px-1">

                                {{-- Permissions --}}
                                <div>
                                    <flux:heading size="sm" class="mb-2">{{ __('Permissions') }}</flux:heading>
                                    <div class="flex flex-wrap gap-4">
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
                                </div>

                                {{-- SFTP --}}
                                @if ($user->permissions->contains('name', 'sftp_access'))
                                    <div>
                                        <flux:heading size="sm" class="mb-2">{{ __('SFTP Account') }}</flux:heading>
                                        @php $sftpUser = $user->sftpUsers->first(); @endphp
                                        @if ($sftpUser)
                                            <flux:text class="font-mono">{{ $sftpUser->username }}</flux:text>
                                            @if ($sftpUser->public_key)
                                                <flux:text size="sm" class="font-mono text-zinc-500 mt-1">
                                                    {{ $sftpUser->public_key_type }} {{ $sftpUser->public_key_fingerprint }}
                                                </flux:text>
                                            @endif
                                        @else
                                            <form wire:submit="createSftpUser({{ $user->id }})" class="space-y-3">
                                                <div class="flex items-end gap-3">
                                                    <flux:input
                                                        wire:model="sftpUsername.{{ $user->id }}"
                                                        :label="__('Username')"
                                                        type="text"
                                                        size="sm"
                                                        placeholder="username"
                                                    />
                                                    <flux:button type="submit" size="sm" variant="primary">
                                                        {{ __('Create') }}
                                                    </flux:button>
                                                    <x-action-message on="sftp-created-{{ $user->id }}">
                                                        {{ __('Created.') }}
                                                    </x-action-message>
                                                </div>
                                                <flux:textarea
                                                    wire:model="sftpPublicKey.{{ $user->id }}"
                                                    :label="__('SSH public key (optional)')"
                                                    :placeholder="__('ssh-ed25519 AAAA...')"
                                                    rows="2"
                                                    class="font-mono text-xs"
                                                />
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                {{-- Future services go here, gated on their respective permission --}}

                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endif
            @endforeach
        </flux:table.rows>
    </flux:table>

</div>
