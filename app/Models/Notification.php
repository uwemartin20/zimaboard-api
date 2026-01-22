<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'message_id',
        'type',
        'created_by',
    ];

    // Relation: all recipients of this notification
    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    // Add a notification for multiple users
    public static function createForUsers(array $userIds, array $data): self
    {
        $notification = self::create($data);

        $recipientData = collect($userIds)->map(fn($userId) => [
            'user_id' => $userId,
            'notification_id' => $notification->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        \DB::table('notification_recipients')->insert($recipientData);

        return $notification;
    }

    /**
     * Create a notification and assign recipients
     *
     * @param string|null $title
     * @param string $body
     * @param string $type
     * @param int|null $messageId
     * @param int $creatorId
     * @param array $userIds
     * @return Notification
     */
    public static function createWithRecipients(
        ?string $title,
        string $body,
        string $type,
        ?int $messageId,
        int $creatorId,
        array $userIds
    ): self {
        // Create the notification
        $notification = self::create([
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'message_id' => $messageId,
            'created_by' => $creatorId,
        ]);

        // Create recipients in bulk
        $recipientData = collect($userIds)->map(fn($userId) => [
            'notification_id' => $notification->id,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        \DB::table('notification_recipients')->insert($recipientData);

        return $notification;
    }

    // Creator of this notification (user/admin)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Optional linked message
    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
