<?php

use App\Models\Order;
use App\Models\User;

describe('Order API', function () {
    describe('Index', function () {
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
                            'status' => $order->status->label(),
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
    });

    describe('Store', function () {
        beforeEach(function () {
            $this->user = User::factory()->create();
            $this->buyOrderPayload = [
                'side' => 'buy',
                'symbol' => 'BTC',
                'price' => 30000.00,
                'amount' => 0.5,
            ];
            $this->sellOrderPayload = [
                'side' => 'sell',
                'symbol' => 'ETH',
                'price' => 2000.00,
                'amount' => 1.0,
            ];
        });

        test('order creation endpoint requires authentication', function () {
            $this->postJson(route('api.orders.store'), [])->assertStatus(401);
        });

        test('authenticated user can not create an buy order if dose not have enough balance', function () {
            $this->user->update(['balance' => 0]);
            $this->actingAs($this->user)->postJson(route('api.orders.store'), $this->buyOrderPayload)
                ->assertStatus(422)
                ->assertJsonPath('message', 'Insufficient balance to place this buy order.');

            $this->assertDatabaseMissing('orders', array_merge($this->buyOrderPayload, [
                'user_id' => $this->user->id,
            ]));
        });

        test('authenticated user can create an buy order with sufficient balance', function () {
            $this->user->update(['balance' => 99999999.99]);
            $this->actingAs($this->user)->postJson(route('api.orders.store'), $this->buyOrderPayload);
            $this->user->refresh();
            $this->assertEquals(99999999.99 - (30000.00 * 0.5), $this->user->balance);
            $this->assertDatabaseHas('orders', array_merge($this->buyOrderPayload, [
                'user_id' => $this->user->id,
                'status' => 1,
            ]));
        });

        test('buy order can match with existing sell order', function () {
            $seller = User::factory(
                [
                    'balance' => 0,
                ]
            )->hasAssets(1, [
                'symbol' => 'BTC',
                'amount' => 0.5,
                'locked_amount' => 0.5,
            ])->create();

            // create a sell order
            $sellOrder = $seller->orders()->create([
                'side' => 'sell',
                'symbol' => 'BTC',
                'price' => 29000.00,
                'amount' => 0.5,
                'status' => 1,
            ]);

            // place a buy order that should match
            $this->user->update(['balance' => 99999999.99]);
            $response = $this->actingAs($this->user)->postJson(route('api.orders.store'), $this->buyOrderPayload);

            // verify balances and assets
            $this->user->refresh();
            $seller->refresh();
            $this->assertEquals((string) (99999999.99 - (30000.00 * 0.5) - ((30000.00 * 0.5) * 0.015)), $this->user->balance);
            $this->assertEquals(0.5, $this->user->assets()->where('symbol', 'BTC')->first()->amount);
            $this->assertSame(29000.00 * 0.5, (float) $seller->balance); // 29000 * 0.5
            $this->assertEquals(0.5, $seller->assets()->where('symbol', 'BTC')->first()->amount);
            $this->assertEquals(0.0, $seller->assets()->where('symbol', 'BTC')->first()->locked_amount);
        });

        test('authenticated user can not create a sell order if dose not have enough asset amount', function () {
            $this->user->assets()->create([
                'symbol' => 'ETH',
                'amount' => 0.5,
                'locked_amount' => 0,
            ]);

            $this->actingAs($this->user)->postJson(route('api.orders.store'), $this->sellOrderPayload)
                ->assertStatus(422)
                ->assertJsonPath('message', 'Insufficient asset amount to place this sell order.');

            $this->assertDatabaseMissing('orders', array_merge($this->sellOrderPayload, [
                'user_id' => $this->user->id,
            ]));
        });

        test('authenticated user can create a sell order with sufficient asset amount', function () {
            $this->user->assets()->create([
                'symbol' => 'ETH',
                'amount' => 10.0,
                'locked_amount' => 0,
            ]);

            $this->actingAs($this->user)->postJson(route('api.orders.store'), $this->sellOrderPayload);
            $this->user->refresh();
            $this->assertEquals(9.0, $this->user->assets()->where('symbol', 'ETH')->first()->amount);
            $this->assertEquals(1.0, $this->user->assets()->where('symbol', 'ETH')->first()->locked_amount);
            $this->assertDatabaseHas('orders', array_merge($this->sellOrderPayload, [
                'user_id' => $this->user->id,
                'status' => 1,
            ]));
        });

        test('sell order can match with existing buy order', function () {
            $buyer = User::factory()
                ->create([
                    'balance' => 99999999.99,
                ]);
            $buyer->orders()->create([
                'side' => 'buy',
                'symbol' => 'ETH',
                'price' => 2100.00,
                'amount' => 1.0,
                'status' => 1,
            ]);

            $this->user->update(['balance' => 0]);
            $this->user->assets()->create([
                'symbol' => 'ETH',
                'amount' => 10.0,
                'locked_amount' => 0,
            ]);

            $response = $this->actingAs($this->user)->postJson(route('api.orders.store'), $this->sellOrderPayload);

            $this->user->refresh();
            $buyer->refresh();
            $this->assertSame(2100.00 * 1.0, (float) $this->user->balance); // 2100 * 1.0
            $this->assertEquals(9.0, $this->user->assets()->where('symbol', 'ETH')->first()->amount);
            $this->assertEquals(0.0, $this->user->assets()->where('symbol', 'ETH')->first()->locked_amount);
            $this->assertEquals((string) (99999999.99 - ((2100.00 * 1.0) * 0.015)), $buyer->balance);
            $this->assertEquals(1.0, $buyer->assets()->where('symbol', 'ETH')->first()->amount);
            $this->assertEquals(0.0, $buyer->assets()->where('symbol', 'ETH')->first()->locked_amount);
        });

    });
});
