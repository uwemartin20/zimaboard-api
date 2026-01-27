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
        ]);

        PushToken::updateOrCreate(
            ['token' => $request->token],
            ['user_id' => $request->user()->id]
        );

        return response()->json(['status' => 'ok']);
    }
}
