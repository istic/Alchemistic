<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sftp_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('username')->unique();
            $table->string('password');
            $table->unsignedInteger('uid')->unique();
            $table->unsignedInteger('gid')->default(2000);
            $table->timestamps();
        });

        Schema::create('sftp_sync', function (Blueprint $table) {
            $table->id();
            $table->boolean('dirty')->default(false);
            $table->timestamp('last_synced_at')->nullable();
        });

        DB::table('sftp_sync')->insert(['dirty' => false, 'last_synced_at' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sftp_users');
        Schema::dropIfExists('sftp_sync');
    }
};
