<?php

use App\Models\Order;
use App\Models\User;

test('order list endpoint requires authentication', function () {
    $this->getJson(route('api.orders.index'))->assertStatus(401);
});

test('authenticated user can access order list endpoint', function () {
    $user = User::factory()->hasOrders(5)->create();

    $this->actingAs($user)->getJson(route('api.orders.index'))
        ->assertStatus(200)
        ->assertJsonCount(5)
        ->assertJsonStructure([
            '*' => ['side', 'symbol', 'price', 'amount', 'status', 'created_at'],
        ])
        ->assertJson(
            $user->orders->map(function ($order) {
                return [
                    'side' => $order->side,
                    'symbol' => $order->symbol,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'status' => $order->status->value,
                    'created_at' => $order->created_at->toISOString(),
                ];
            })->toArray()
        );
});

test('order list filter by symbol as query parameter', function () {
    $user = User::factory()->create();
    Order::factory()->count(2)->sequence(
        ['symbol' => 'BTC'],
        ['symbol' => 'ETH']
    )->for($user)->create();

    $this->actingAs($user)->getJson(route('api.orders.index', ['symbol' => 'BTC']))
        ->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['symbol' => 'BTC'])
        ->assertJsonMissing(['symbol' => 'ETH']);
});
