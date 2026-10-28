<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PushToken;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Upsert push token
        $push = PushToken::updateOrCreate(
            ['token' => $request->input('token')],
            ['user_id' => $user->id, 'platform' => $request->input('platform')]
        );

        return response()->json(['success' => true]);
    }
}
