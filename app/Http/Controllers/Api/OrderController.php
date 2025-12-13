<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->orders()
            ->where('status', OrderStatus::OPEN)
            ->when($request->has('symbol'), fn ($query) => $query->where('symbol', $request->query('symbol')))
            ->get()
            ->toResourceCollection();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'side' => 'required|in:buy,sell',
            'symbol' => 'required|string',
            'price' => 'required|numeric',
            'amount' => 'required|numeric',
        ]);

        $user = $request->user();

        if ($data['side'] === 'buy' && $user->doseNotHaveSufficientBalance($data['price'] * $data['amount'])) {
            logger('throwing insufficient balance');
            throw ValidationException::withMessages([
                'balance' => ['Insufficient balance to place this buy order.'],
            ])->status(422);
        }

        if ($data['side'] === 'sell') {
            if ($user->doseNotHaveSufficientAsset($data['symbol'], $data['amount'])) {
                throw ValidationException::withMessages([
                    'asset' => ['Insufficient asset amount to place this sell order.'],
                ]);
            }
        }

        DB::transaction(function () use ($data, $user) {
            if ($data['side'] === 'buy') {
                $amount = $data['price'] * $data['amount'];
                $user->decrement('balance', $amount);
                $order = $user->orders()->create($data + ['status' => OrderStatus::OPEN]);
                // find matching sell orders
                $matchingSellOrder = Order::where('symbol', $data['symbol'])
                    ->where('side', 'sell')
                    ->where('price', '<=', $data['price'])
                    ->where('amount', $data['amount'])
                    ->where('status', OrderStatus::OPEN)
                    ->orderBy('price', 'asc')
                    ->first();
                if ($matchingSellOrder) {
                    // mark both orders as filled
                    $order->update(['status' => OrderStatus::FILLED]);
                    $matchingSellOrder->update(['status' => OrderStatus::FILLED]);
                    // credit the seller's balance
                    $seller = $matchingSellOrder->user;
                    $seller->increment('balance', $matchingSellOrder->price * $matchingSellOrder->amount);
                    // release locked asset from seller
                    $seller->assets()->where('symbol', $matchingSellOrder->symbol)->decrement('locked_amount', $matchingSellOrder->amount);
                    // credit the buyer's asset
                    $user->incrementAsset($data['symbol'], $data['amount']);
                    // commission fee deducted from buyer *0.015
                    $commission = ($order->price * $order->amount) * 0.015;
                    $user->decrement('balance', $commission);
                }
            } else {
                $user->decrementAsset($data['symbol'], $data['amount']);
                $order = $user->orders()->create($data + ['status' => OrderStatus::OPEN]);
                // find matching buy orders
                $matchingBuyOrder = Order::where('symbol', $data['symbol'])
                    ->where('side', 'buy')
                    ->where('price', '>=', $data['price'])
                    ->where('amount', $data['amount'])
                    ->where('status', OrderStatus::OPEN)
                    ->orderBy('price', 'desc')
                    ->first();
                if ($matchingBuyOrder) {
                    // mark both orders as filled
                    $order->update(['status' => OrderStatus::FILLED]);
                    $matchingBuyOrder->update(['status' => OrderStatus::FILLED]);
                    // credit the seller's balance
                    $user->increment('balance', $matchingBuyOrder->price * $matchingBuyOrder->amount);
                    // release locked asset from seller
                    $user->assets()->where('symbol', $data['symbol'])->decrement('locked_amount', $data['amount']);
                    // credit the buyer's asset
                    $buyer = $matchingBuyOrder->user;
                    $buyer->incrementAsset($data['symbol'], $data['amount']);

                    // commission fee deducted from buyer *0.015
                    $commission = ($matchingBuyOrder->price * $matchingBuyOrder->amount) * 0.015;
                    $buyer->decrement('balance', $commission);
                }
            }
        });

        return back()->with('status', 'Order placed successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $order = $request->user()->orders()->where('id', $id)->where('status', OrderStatus::OPEN)->firstOrFail();

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::CANCELLED]);

            if ($order->side === 'buy') {
                // refund balance to buyer
                $refundAmount = $order->price * $order->amount;
                $order->user->increment('balance', $refundAmount);
            } else {
                // release locked asset for seller
                $order->user->assets()->where('symbol', $order->symbol)->decrement('locked_amount', $order->amount);
                // increment asset amount back to seller
                $order->user->assets()->where('symbol', $order->symbol)->increment('amount', $order->amount);
            }
        });

        return back()->with('status', 'Order cancelled successfully.');
    }
}
