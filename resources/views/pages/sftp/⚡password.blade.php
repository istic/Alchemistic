<?php

use App\Rules\SshPublicKey;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('SFTP Access')] class extends Component {
    public string $username = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $public_key = '';

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(
            ! $user->hasPermission('sftp_access') && ! $user->hasPermission('admin'),
            403
        );
    }

    public function createAccount(): void
    {
        if ($this->public_key) {
            $this->public_key = SshPublicKey::normalize($this->public_key);
        }

        $this->validate([
            'username'   => ['required', 'string', 'max:32', 'alpha_dash', 'unique:sftp_users,username'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'public_key' => ['nullable', 'string', new SshPublicKey],
        ]);

        $user = Auth::user();

        abort_if($user->sftpUsers()->exists(), 422, 'SFTP account already exists.');

        $user->sftpUsers()->create([
            'username'   => $this->username,
            'password'   => $this->password,
            'public_key' => $this->public_key ?: null,
        ]);

        $this->reset('username', 'password', 'password_confirmation', 'public_key');

        $this->dispatch('sftp-account-created');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $sftpUser = Auth::user()->sftpUsers()->first();

        abort_if(! $sftpUser, 403, 'No SFTP account found.');

        $sftpUser->update(['password' => $this->password]);

        $this->reset('password', 'password_confirmation');

        $this->dispatch('sftp-password-updated');
    }

    public function updatePublicKey(): void
    {
        if ($this->public_key) {
            $this->public_key = SshPublicKey::normalize($this->public_key);
        }

        $this->validate([
            'public_key' => ['nullable', 'string', new SshPublicKey],
        ]);

        $sftpUser = Auth::user()->sftpUsers()->first();

        abort_if(! $sftpUser, 403, 'No SFTP account found.');

        $sftpUser->update(['public_key' => $this->public_key ?: null]);

        $this->dispatch('sftp-key-updated');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-lg">

    <flux:heading size="xl">{{ __('SFTP Access') }}</flux:heading>

    @php $sftpUser = Auth::user()->sftpUsers()->first(); @endphp

    @if ($sftpUser)
        <flux:card class="space-y-6">
            <div>
                <flux:label>{{ __('Username') }}</flux:label>
                <flux:text class="mt-1 font-mono">{{ $sftpUser->username }}</flux:text>
            </div>

            <form wire:submit="updatePassword" class="space-y-4">
                <flux:heading size="lg">{{ __('Change Password') }}</flux:heading>
                <flux:input
                    wire:model="password"
                    :label="__('New password')"
                    type="password"
                    required
                    autocomplete="new-password"
                />
                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                />
                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Update Password') }}</flux:button>
                    <x-action-message on="sftp-password-updated">{{ __('Saved.') }}</x-action-message>
                </div>
            </form>

            <flux:separator />

            <div class="space-y-4">
                <flux:heading size="lg">{{ __('SSH Public Key') }}</flux:heading>

                @if ($sftpUser->public_key)
                    <div class="space-y-1">
                        <flux:label>{{ __('Current key') }}</flux:label>
                        <flux:text class="font-mono text-sm">{{ $sftpUser->public_key_type }}</flux:text>
                        <flux:text class="font-mono text-sm text-zinc-500">{{ $sftpUser->public_key_fingerprint }}</flux:text>
                    </div>
                @endif

                <form wire:submit="updatePublicKey" class="space-y-4">
                    <flux:textarea
                        wire:model="public_key"
                        :label="$sftpUser->public_key ? __('Replace key') : __('Public key')"
                        :placeholder="__('ssh-ed25519 AAAA...')"
                        rows="3"
                        class="font-mono text-sm"
                    />
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Leave blank to remove key and use password authentication only.') }}
                    </flux:text>
                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit">{{ __('Update Key') }}</flux:button>
                        <x-action-message on="sftp-key-updated">{{ __('Saved.') }}</x-action-message>
                    </div>
                </form>
            </div>
        </flux:card>
    @else
        <flux:card>
            <form wire:submit="createAccount" class="space-y-4">
                <flux:heading size="lg">{{ __('Create SFTP Account') }}</flux:heading>
                <flux:text>{{ __('Choose a username and password for your SFTP account.') }}</flux:text>

                <flux:input
                    wire:model="username"
                    :label="__('Username')"
                    type="text"
                    required
                    autocomplete="username"
                />
                <flux:input
                    wire:model="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                />
                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                />
                <flux:textarea
                    wire:model="public_key"
                    :label="__('SSH public key (optional)')"
                    :placeholder="__('ssh-ed25519 AAAA...')"
                    rows="3"
                    class="font-mono text-sm"
                />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">
                        {{ __('Create Account') }}
                    </flux:button>

                    <x-action-message on="sftp-account-created">
                        {{ __('Account created.') }}
                    </x-action-message>
                </div>
            </form>
        </flux:card>
    @endif

</div>
