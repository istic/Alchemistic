<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Console\Command;

class GrantPermission extends Command
{
    protected $signature = 'permission:grant {user : Email address of the user} {permission : Permission name}';

    protected $description = 'Grant a permission to a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('user'))->first();

        if (! $user) {
            $this->error("User [{$this->argument('user')}] not found.");
            return self::FAILURE;
        }

        $permission = Permission::where('name', $this->argument('permission'))->first();

        if (! $permission) {
            $available = Permission::orderBy('name')->pluck('name')->join(', ');
            $this->error("Permission [{$this->argument('permission')}] not found. Available: {$available}");
            return self::FAILURE;
        }

        if ($user->permissions()->where('permission_id', $permission->id)->exists()) {
            $this->info("User [{$user->email}] already has permission [{$permission->name}].");
            return self::SUCCESS;
        }

        $user->permissions()->attach($permission);
        $this->info("Granted [{$permission->name}] to [{$user->email}].");

        return self::SUCCESS;
    }
}
