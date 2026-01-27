<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    public static function send(
        array $tokens,
        string $title,
        string $body,
        array $data = []
    ): void {
        if (empty($tokens)) {
            return;
        }

        $messages = collect($tokens)->map(fn ($token) => [
            'to'    => $token,
            'sound' => 'default',
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
        ])->values()->toArray();

        $response = Http::post(
            'https://exp.host/--/api/v2/push/send',
            $messages
        );

        Log::info('Expo push response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);
    }
}
