<?php

use App\Http\Controllers\OAuth\DiscoveryController;
use App\Http\Controllers\OAuth\JwksController;
use App\Http\Controllers\OAuth\TokenController;
use App\Http\Controllers\OAuth\UserInfoController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;

Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->middleware('web')
    ->name('passport.authorizations.authorize');

Route::post('/oauth/token', [TokenController::class, 'issueToken'])
    ->middleware('throttle')
    ->name('passport.token');

Route::get('/oauth/userinfo', [UserInfoController::class, 'show'])
    ->middleware('auth:api')
    ->name('oidc.userinfo');

Route::get('/oauth/jwks', JwksController::class)->name('oidc.jwks');

Route::get('/.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');
