<?php

use App\Models\User;

test('profile endpoint requires authentication', function () {
    $this->getJson(route('api.profile'))->assertStatus(401);
});

test('authenticated user can access profile endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('api.profile'))
        ->assertStatus(200)
        ->assertJsonPath('balance', $user->balance);
});

test('profile endpoint returns user assets', function () {
    $user = User::factory()->hasAssets(3)->create();
    $this->actingAs($user)->getJson(route('api.profile'))
        ->assertStatus(200)
        ->assertJsonCount(3, 'assets')
        ->assertJsonPath(
            'assets',
            $user->assets->map(function ($asset) {
                return [
                    'symbol' => $asset->symbol,
                    'amount' => $asset->amount,
                    'locked_amount' => $asset->locked_amount,
                ];
            })->toArray()
        );
});
