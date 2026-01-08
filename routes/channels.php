<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('{env}.user.{id}', function ($user, $env, $id) {
    return (int) $user->id === (int) $id;
});

// Broadcast::channel('message.{messageId}', function ($user, $messageId) {
//     return $user->messages()
//                 ->where('messages.id', $messageId)
//                 ->exists();
// });
