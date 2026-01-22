<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List all notifications for a user
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $notifications = NotificationRecipient::with(['notification.creator', 'notification.message'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($recipient) {
                return [
                    'recipient_id' => $recipient->id,
                    'read_at' => $recipient->read_at,
                    'notification' => [
                        'id' => $recipient->notification->id,
                        'title' => $recipient->notification->title,
                        'body' => $recipient->notification->body,
                        'type' => $recipient->notification->type,
                        'creator' => $recipient->notification->creator?->only(['id', 'name', 'email']),
                        'message' => $recipient->notification->message?->only(['id', 'body']),
                        'created_at' => $recipient->notification->created_at,
                    ]
                ];
            });

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    /**
     * Create a new notification for multiple users
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'required|string',
            'type' => 'required|string|max:50',
            'message_id' => 'nullable|exists:messages,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $notification = Notification::create([
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'type' => $data['type'],
            'message_id' => $data['message_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // Create recipients
        $recipientData = collect($data['user_ids'])->map(fn($userId) => [
            'notification_id' => $notification->id,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        \DB::table('notification_recipients')->insert($recipientData);

        return response()->json(['success' => true, 'data' => $notification]);
    }

    /**
     * Update a notification (title/body/type/message_id)
     * Also allows updating recipients (replace old users with new ones)
     */
    public function update(Request $request, Notification $notification)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'message_id' => 'nullable|exists:messages,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Update notification
        $notification->update(array_filter([
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'type' => $data['type'] ?? null,
            'message_id' => $data['message_id'] ?? null,
        ]));

        // Update recipients if user_ids provided
        if (!empty($data['user_ids'])) {
            // Remove old recipients not in new list
            NotificationRecipient::where('notification_id', $notification->id)
                ->whereNotIn('user_id', $data['user_ids'])
                ->delete();

            // Insert new recipients
            $existingUserIds = NotificationRecipient::where('notification_id', $notification->id)
                ->pluck('user_id')
                ->toArray();

            $newUserIds = array_diff($data['user_ids'], $existingUserIds);

            $recipientData = collect($newUserIds)->map(fn($userId) => [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            if (!empty($recipientData)) {
                \DB::table('notification_recipients')->insert($recipientData);
            }
        }

        return response()->json(['success' => true, 'data' => $notification]);
    }

    /**
     * Delete a notification recipients
     */
    public function markAllAsRead()
    {
        NotificationRecipient::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'data' => 'Alle Benachrichtigungen als gelesen markiert']);
    }

    public function destroy(NotificationRecipient $recipient)
    {
        abort_if($recipient->user_id !== auth()->id(), 403);

        DB::transaction(function () use ($recipient) {
            $notificationId = $recipient->notification_id;

            // Delete this recipient
            $recipient->delete();

            // Check if any recipients remain
            $hasRemainingRecipients = NotificationRecipient::where(
                'notification_id',
                $notificationId
            )->exists();

            // If no recipients left → delete notification
            if (! $hasRemainingRecipients) {
                Notification::where('id', $notificationId)->delete();
            }
        });
        return response()->json(['success' => true, 'data' => 'Benachrichtigung gelöscht']);
    }

    /**
     * Mark a notification as read by the user
     */
    public function markAsRead(NotificationRecipient $recipient)
    {
        abort_if($recipient->user_id !== auth()->id(), 403);

        if (!$recipient->read_at) {
            $recipient->update(['read_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => 'Als gelesen markiert']);
    }

    /**
     * Get minimal detail of a notification for API
     */
    public function show(Notification $notification, Request $request)
    {
        $recipient = NotificationRecipient::where('notification_id', $notification->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'notification' => $notification->only(['id','title','body','type','message_id','created_by','created_at']),
                'recipient' => $recipient?->only(['id','read_at']),
                'creator' => $notification->creator?->only(['id','name','email']),
                'message' => $notification->message?->only(['id','body']),
            ]
        ]);
    }
}
