<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('trigger-event', function () {
    $comment = App\Models\ChatMessage::first();
    broadcast(new App\Events\ChatCreated($comment));
    return 'Event has been sent!';
});

Route::get('notification-event', function () {
    $notification = App\Models\Notification::first();
    broadcast(new App\Events\NotificationCreated(
        recipient: $notification->recipients->first(),
        notification: $notification
    ))->toOthers();
    return 'Notification Event has been sent!';
});
