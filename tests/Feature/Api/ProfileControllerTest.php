<?php

use App\Models\User;

test('profile endpoint requires authentication', function () {
    $response = $this->getJson(route('api.profile'));

    $response->assertStatus(401);
});

test('authenticated user can access profile endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('api.profile'))
        ->assertStatus(200)
        ->assertJsonPath('balance', $user->balance);
});

test('profile endpoint returns user assets', function () {
    $user = User::factory()
        ->hasAssets(3)
        ->create();

    $this->actingAs($user)->getJson(route('api.profile'))
        ->assertStatus(200)
        ->assertJsonCount(3, 'assets')
        ->assertJsonPath('assets.0.symbol', $user->assets->first()->symbol)
        ->assertJsonPath('assets.0.amount', $user->assets->first()->amount)
        ->assertJsonPath('assets.0.locked_amount', $user->assets->first()->locked_amount);
});
