<?php

test('registration screen can be rendered', function () {
    if (!config('fortify.features') || !in_array(Laravel\Fortify\Features::registration(), config('fortify.features'))) {
        $this->markTestSkipped('Registration is disabled.');
    }
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    if (!config('fortify.features') || !in_array(Laravel\Fortify\Features::registration(), config('fortify.features'))) {
        $this->markTestSkipped('Registration is disabled.');
    }
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
