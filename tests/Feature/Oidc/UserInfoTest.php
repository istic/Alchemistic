<?php

use App\Models\Permission;
use App\Models\User;
use Laravel\Passport\Passport;

test('userinfo returns oidc claims for the token owner', function () {
    $user = User::factory()->unverified()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    $permission = Permission::create(['name' => 'admin', 'label' => 'Administrator']);
    $user->permissions()->attach($permission);

    Passport::actingAs($user, ['openid']);

    $response = $this->getJson('/oauth/userinfo');

    $response->assertOk()->assertJson([
        'sub' => (string) $user->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'email_verified' => false,
        'permissions' => ['admin'],
    ]);
});

test('userinfo rejects a token without the openid scope', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['profile']);

    $this->getJson('/oauth/userinfo')->assertStatus(403);
});

test('userinfo rejects a garbage bearer token', function () {
    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->getJson('/oauth/userinfo')
        ->assertStatus(401);
});

test('userinfo rejects requests with no bearer token at all', function () {
    $this->getJson('/oauth/userinfo')->assertStatus(401);
});

test('userinfo reports an empty permissions array for a user with none', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['openid']);

    $response = $this->getJson('/oauth/userinfo');

    $response->assertOk()->assertJson(['permissions' => []]);
});
