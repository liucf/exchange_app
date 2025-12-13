<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\OrderStatus;
use Illuminate\Http\JsonResponse;
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'side' => 'required|in:buy,sell',
            'symbol' => 'required|string',
            'price' => 'required|numeric',
            'amount' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            if ($data['side'] === 'buy') {
                $amount = $data['price'] * $data['amount'];
                if ($user->doseNotHaveSufficientBalance($amount)) {
                    throw ValidationException::withMessages([
                        'balance' => 'Insufficient balance to place this buy order.',
                    ]);
                }
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

                return response()->json([
                    'user_id' => $user->id,
                    'side' => 'buy',
                    'order' => new OrderResource($order),
                    'matched_order' => $matchingSellOrder ? new OrderResource($matchingSellOrder) : null,
                ]);
            } else {
                if ($user->doseNotHaveSufficientAsset($data['symbol'], $data['amount'])) {
                    throw ValidationException::withMessages([
                        'asset' => 'Insufficient asset amount to place this sell order.',
                    ]);
                }
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

                return response()->json([
                    'user_id' => $user->id,
                    'side' => 'sell',
                    'order' => new OrderResource($order),
                    'matched_order' => $matchingBuyOrder ? new OrderResource($matchingBuyOrder) : null,
                ]);
            }
        });
    }
}
