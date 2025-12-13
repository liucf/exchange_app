<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\OrderStatus;
use Illuminate\Http\Request;

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
}
