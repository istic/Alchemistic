<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
    Route::livewire('sftp/password', 'pages::sftp.password')->name('sftp.password');
});

require __DIR__ . '/settings.php';
