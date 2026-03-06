<?php

namespace App\Observers;

use App\Models\SftpUser;
use Illuminate\Support\Facades\DB;

class SftpUserObserver
{
    public function created(SftpUser $sftpUser): void
    {
        $this->markDirty();
    }

    public function updated(SftpUser $sftpUser): void
    {
        $this->markDirty();
    }

    public function deleted(SftpUser $sftpUser): void
    {
        $this->markDirty();
    }

    private function markDirty(): void
    {
        DB::table('sftp_sync')->where('id', 1)->update(['dirty' => true]);
    }
}
