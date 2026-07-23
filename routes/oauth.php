<?php

use App\Http\Controllers\OAuth\TokenController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;

Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->middleware('web')
    ->name('passport.authorizations.authorize');

Route::post('/oauth/token', [TokenController::class, 'issueToken'])
    ->middleware('throttle')
    ->name('passport.token');
