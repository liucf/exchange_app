<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance' => $this->balance,
            'assets' => AssetResource::collection($this->whenLoaded('assets')),
        ];
    }
}
