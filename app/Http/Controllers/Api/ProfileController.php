<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request): ProfileResource
    {
        $user = $request->user()->load('assets');

        return new ProfileResource($user);
    }
}
