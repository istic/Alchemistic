<?php

use App\Models\Permission;
use App\Models\SftpUser;
use App\Models\User;
use Livewire\Livewire;

test('admin can create an sftp user without manually providing a password', function () {
    $admin = User::factory()->create();
    $managedUser = User::factory()->create();

    $adminPermission = Permission::create([
        'name' => 'admin',
        'label' => 'Admin',
    ]);

    $admin->permissions()->attach($adminPermission);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users')
        ->set('sftpUsername.'.$managedUser->id, 'managed_user')
        ->call('createSftpUser', $managedUser->id);

    $response->assertHasNoErrors();

    $sftpUser = SftpUser::query()
        ->where('user_id', $managedUser->id)
        ->first();

    expect($sftpUser)->not->toBeNull();
    expect($sftpUser?->password)->toBeNull();

    $this->assertDatabaseHas('sftp_users', [
        'user_id' => $managedUser->id,
        'username' => 'managed_user',
    ]);
});
