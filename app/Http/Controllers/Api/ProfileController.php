<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $assets = $user->assets()->get()->map(function ($asset) {
            return [
                'symbol' => $asset->symbol,
                'amount' => $asset->amount,
                'locked_amount' => $asset->locked_amount,
                'available_amount' => $asset->available_amount,
            ];
        });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'balance' => $user->balance,
            ],
            'assets' => $assets,
        ]);
    }
}
