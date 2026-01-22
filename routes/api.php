<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [AuthController::class, 'users']);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{recipient}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{recipient}', [NotificationController::class, 'destroy']);

    Route::get('/created', [MessageController::class, 'created']);
    Route::get('/assigned', [MessageController::class, 'assigned']);
    Route::get('/announcement', [MessageController::class, 'announcements']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/message-statuses', [MessageController::class,'messageStatuses']);
    Route::get('/messages/{message}', [MessageController::class,'show']);
    Route::post('/new-message', [MessageController::class, 'store']);
    Route::put('/message-update/{message}', [MessageController::class,'updateMessage']);
    Route::post('/store-attachments', [MessageController::class, 'storeAttachment']);
    Route::post('/store-activities', [MessageController::class, 'storeActivity']);
    Route::post('/messages/{message}/assign', [MessageController::class, 'assign']);
    Route::put('/messages/{message}', [MessageController::class, 'update']);
    Route::put('/messages/{message}/assign-to-me', [MessageController::class, 'assignToMe']);
    Route::post('/messages/{message}/comments', [MessageController::class, 'addComment']);
    Route::post('/users/change-password', [AuthController::class,'changePassword']);

    Route::middleware('is_admin')->prefix('settings')->group(function () {

        Route::apiResource('departments', DepartmentController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('statuses', StatusController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('users', UserController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
});

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
