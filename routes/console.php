<?php

use App\Console\Commands\AddUser;
use Illuminate\Support\Facades\Artisan;

Artisan::command('auth:add-user', function () {
    (new AddUser)->handle();
})->purpose('Add a new user');
