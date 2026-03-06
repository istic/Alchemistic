<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'admin', 'label' => 'Admin'],
            ['name' => 'sftp_access', 'label' => 'SFTP Access'],
            ['name' => 'imap_access', 'label' => 'IMAP Access'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
