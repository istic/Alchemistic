<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Console\Command;

class AddUser extends Command
{
    protected $signature = 'auth:add-user {user : Email address of the user} {password : Password for the user} {--permission= : Comma-separated list of permissions to grant}';

    protected $description = 'Add a new user';

    public function handle()
    {
        $this->comment('Add a new user');
        $userEmail = $this->argument('user');
        if (! $userEmail = $this->argument('user')) {
            $userEmail = $this->ask('Enter email address for the new user');
        }
        if ($userPassword = $this->argument('password')) {
            $userPassword = $this->argument('password');
        } else {
            $userPassword = $this->secret('Enter password for the new user, or leave empty to generate a random password');

            if (empty($userPassword)) {
                $userPassword = bin2hex(random_bytes(8)); // Generate a random password
                $this->info("Generated random password: {$userPassword}");
            }
        }

        $user = User::create([
            'email' => $userEmail,
            'password' => bcrypt($userPassword),
        ]);
        $this->info("User [{$userEmail}] created successfully.");
        foreach (explode(',', $this->option('permission')) as $permissionName) {
            $permissionName = trim($permissionName);
            if (empty($permissionName)) {
                $this->error('Permission name cannot be empty.');

                continue;
            }
            if ($permission = Permission::where('name', $permissionName)->first()) {
                $user->permissions()->attach($permission);
                $this->info("Granted permission [{$permissionName}] to user [{$userEmail}].");
            } else {
                $available = Permission::orderBy('name')->pluck('name')->join(', ');
                $this->error("Permission [{$permissionName}] not found. Available: {$available}");
            }
        }
    }
}
