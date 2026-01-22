<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'user_id',
        'read_at',
    ];

    protected $dates = [
        'read_at',
    ];

    // Relation: the parent notification
    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    // Belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Mark this notification as read
    public function markAsRead(): self
    {
        $this->read_at = now();
        $this->save();
        return $this;
    }
}
