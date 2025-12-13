<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'side' => $this->side,
            'symbol' => $this->symbol,
            'price' => $this->price,
            'amount' => $this->amount,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
