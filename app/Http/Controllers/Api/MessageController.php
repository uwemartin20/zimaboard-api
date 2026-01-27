<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Attachment;
use App\Models\Activity;
use Illuminate\Http\Request;
use App\Events\NewMessage;
use App\Events\ChatCreated;
use App\Services\ExpoPushService;
use App\Events\NotificationCreated;
use App\Models\Notification;

class MessageController extends Controller
{
    /**
     * Board: Created by current user
     */
    public function created(Request $request)
    {
        $user = $request->user();

        $query = Message::query()
            ->where('creator_id', $user->id);
            // ->where('is_announcement', false);

        // Optional filters
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn($q) => $q->where('name', $request->status));
        }

        if ($request->filled('creator_id')) {
            $query->where('creator_id', $request->creator_id);
        }

        $messages = $query->with($this->relations())
            ->latest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Board: Assigned to current user
     * (Created by someone else)
     */
    public function assigned(Request $request)
    {
        $user = $request->user();

        $query = Message::query()
            ->where('assigned_to', $user->id);

        // $query = Message::query()
        //     ->whereHas('assignees', function ($q) use ($user) {
        //         $q->where('users.id', $user->id);
        //     })
        //     ->where('is_announcement', false);

        // Optional filters
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn($q) => $q->where('name', $request->status));
        }

        if ($request->filled('creator_id')) {
            $query->where('creator_id', $request->creator_id);
        }

        $messages = $query->with($this->relations())
            ->latest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Board: Announcements (visible to everyone)
     */
    public function announcements(Request $request)
    {
        $user = $request->user();

        $query = Message::query()
        ->whereHas('assignees', function ($q) use ($user) {
            $q->where('users.id', $user->id); // current user is a subscriber
        })
        ->where(function ($q) use ($user) {
            $q->where('assigned_to', '<>', $user->id)
              ->orWhereNull('assigned_to'); // include if assigned_to is null
        })
        ->where(function ($q) use ($user) {
            $q->where('creator_id', '<>', $user->id)
              ->orWhereNull('creator_id'); // include if creator_id is null
        });

        // $query = Message::query()
        //     ->where('is_announcement', true);

        // Optional filters
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn($q) => $q->where('name', $request->status));
        }

        if ($request->filled('creator_id')) {
            $query->where('creator_id', $request->creator_id);
        }

        $messages = $query->with($this->relations())
            ->latest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Shared eager-load relations
     */
    protected function relations(): array
    {
        return [
            'creator:id,name,department_id',
            'creator.department:id,name,color',
            'assignees:id,name',
            'assignee:id,name',
            'status:id,name,color',
            'chatMessages:id,message_id,user_id,content,created_at',
            'chatMessages.user:id,name',
            'activities:id,message_id,user_id,action,assignee_id,created_at',
            'activities.user:id,name',
            'activities.assignee:id,name',
            'attachments:id,message_id,chat_message_id,path,original_name,mime_type,size',
        ];
    }

    public function show(Message $message)
    {
        // Load all relations defined in the relations() helper
        $message->load($this->relations());

        return response()->json($message);
    }
    /**
     * Store a new message
     */
    public function store(Request $request)
    {

        $validated = $request->validate( [
            'title'         => 'required|string|max:255',
            'description'   => 'required|string',
            'priority'      => 'required|string|in:Niedrig,Mittel,Hoch',
            'status_id'     => 'required|exists:message_statuses,id',
            'assignees'     => 'array',
            'assignees.*'   => 'exists:users,id',
            'assignee'      => 'nullable|exists:users,id',
            'is_announcement' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        $message = Message::create([
            'title'           => $validated['title'],
            'description'     => $validated['description'],
            'priority'        => $validated['priority'],
            'status_id'       => $validated['status_id'],
            'creator_id'      => $user->id,
            'assigned_to'       => $validated['assignee'] ?? null,
            'department_id'   => $user->department_id,
            'is_announcement' => $validated['is_announcement'] ?? false,
        ]);
    
        // Attach assignees (if any)
        if (!empty($validated['assignees'])) {
            $message->assignees()->attach(
                collect($validated['assignees'])->mapWithKeys(fn ($id) => [
                    $id => ['assigned_by' => $user->id]
                ])->toArray()
            );
        }

        $userIds = collect($validated['assignees'] ?? [])
        ->push($validated['assignee'] ?? null) // add single assignee
        ->filter()                             // remove nulls
        ->unique()                             // remove duplicates
        ->values()                             // reset keys
        ->toArray();

        // Notify recipient(s)
        $notification = Notification::createWithRecipients(
            title: "Neue Nachricht: " . $message->title . "von " . $user->name,
            body: $message->description,
            type: "message_created",
            messageId: $message->id,
            creatorId: auth()->id(),
            userIds: $userIds
        );

        foreach ($notification->recipients as $recipient) {
            broadcast(new NotificationCreated(
                recipient: $recipient,
                notification: $notification
            ))->toOthers();

            $tokens = $recipient->user->pushTokens()->pluck('token')->filter()->toArray();

            ExpoPushService::send(
                $tokens,
                'Neue Nachricht Erhalten',
                $user->name . ' hat ein Nachricht "' . $message->title . '" Erstellt.',
                [
                    'type' => 'chat',
                    'messageId' => $message->id,
                ]
            );
        }

        // broadcast(new NewMessage($message));
    
        return response()->json([
            'message' => 'Nachricht erfolgreich erstellt',
            'data' => $message->load('assignees:id,name'),
        ], 201);
    }

    /**
     * Update an existing message
     */
    public function updateMessage(Request $request, Message $message)
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'required|string',
            'priority'        => 'required|string|in:Niedrig,Mittel,Hoch',
            'status_id'       => 'required|exists:message_statuses,id',
            'assignees'       => 'array',
            'assignees.*'     => 'exists:users,id',
            'assignee'          => 'nullable|exists:users,id',
            'is_announcement' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        /**
         * (Optional but recommended)
         * Authorization check
         */
        $isCreator = $message->creator_id === $user->id;

        $isAssignee = $message->assignees()
            ->where('users.id', $user->id)
            ->exists();

        if (! $isCreator && ! $isAssignee) {
            abort(403, 'Nicht berechtigt, diese Nachricht zu bearbeiten');
        }

        /**
         * Update message fields
         */
        $message->update([
            'title'           => $validated['title'],
            'description'     => $validated['description'],
            'priority'        => $validated['priority'],
            'status_id'       => $validated['status_id'],
            'assigned_to'       => $validated['assignee'] ?? null,
            'is_announcement' => $validated['is_announcement'] ?? false,
        ]);

        /**
         * Sync assignees
         * - removes unselected
         * - adds new
         * - updates pivot metadata
         */
        if (array_key_exists('assignees', $validated)) {
            $syncData = collect($validated['assignees'])->mapWithKeys(fn ($id) => [
                $id => ['assigned_by' => $user->id],
            ])->toArray();

            $message->assignees()->sync($syncData);
        }

        /**
         * Optional: broadcast update
         */
        // broadcast(new MessageUpdated($message));

        return response()->json([
            'message' => 'Nachricht erfolgreich aktualisiert',
            'data'    => $message->load($this->relations()),
        ]);
    }

    public function messageStatuses()
    {
        $statuses = \App\Models\MessageStatus::all();

        return response()->json($statuses);
    }

    public function storeAttachment(Request $request)
    {
        $request->validate([
            'message_id'      => 'required|exists:messages,id',
            'chat_message_id' => 'nullable|exists:chat_messages,id',
            'files'         => 'required|array',
            'files.*'       => 'required|file|max:10240',
        ]);

        $attachments = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store('attachments', 'public');

            $attachments[] = Attachment::create([
                'message_id'      => $request->message_id,
                'chat_message_id' => $request->chat_message_id ?? null,
                'path'            => $path,
                'original_name'   => $file->getClientOriginalName(),
                'mime_type'       => $file->getClientMimeType(),
                'size'            => $file->getSize(),
            ]);
        }

        return response()->json([
            'message' => 'Anhang erfolgreich hochgeladen',
            'data'    => $attachments,
        ], 201);
    }

    public function storeActivity(Request $request)
    {
        $request->validate([
            'message_id'  => 'required|exists:messages,id',
            'action'      => 'required|string|max:255',
            'assignee_id' => 'nullable|exists:users,id',
        ]);

        $activity = Activity::create([
            'message_id'  => $request->message_id,
            'user_id'     => $request->user()->id, // Use authenticated user
            'action'      => $request->action,
            'assignee_id' => $request->assignee_id,
        ]);

        return response()->json([
            'message' => 'Aktivität erfolgreich erstellt',
            'data'    => $activity,
        ], 201);
    }

    public function assign(Request $request, Message $message)
    {
        $request->validate([
            'assignees'   => 'required|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Attach new assignees (without detaching existing)
        foreach ($request->assignees as $userId) {
            $message->assignees()->syncWithoutDetaching([$userId => ['assigned_by' => $request->user()->id]]);
        }

        // Log activity
        foreach ($request->assignees as $assigneeId) {
            $message->activities()->firstOrCreate([
                'user_id' => $request->user()->id,
                'action'  => 'assigned to',
                'assignee_id' => $assigneeId,
            ]);
        }

        $message->update(["is_announcement" => false]);

        return response()->json([
            'message' => 'Zugewiesene wurden erfolgreich aktualisiert',
            'data' => $message->load(['assignees', 'activities'])
        ]);
    }

    public function update(Request $request, Message $message)
    {
        $request->validate([
            'is_archived' => 'required|boolean',
        ]);

        $message->is_archived = $request->is_archived;
        $message->save();

        // Log activity
        $message->activities()->create([
            'user_id' => $request->user()->id,
            'action' => $request->is_archived ? 'archived message' : 'unarchived message',
            'assignee_id' => null,
        ]);

        return response()->json([
            'message' => 'Nachricht erfolgreich aktualisiert',
            'data' => $message
        ]);
    }

    public function assignToMe(Request $request, Message $message)
    {
        $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $message->assigned_to = $request->assigned_to;
        $message->save();

        // Log activity
        $message->activities()->create([
            'user_id' => $request->user()->id,
            'action' => $request->user()->name . " sich selbst zugewiesen",
            'assignee_id' => null,
        ]);

        return response()->json([
            'message' => 'Nachricht erfolgreich zugewiesen',
            'data' => $message
        ]);
    }

    public function addComment(Request $request, Message $message)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        $comment = $message->chatMessages()->create([
            'user_id' => $user->id,
            'content' => $request->text,
        ]);

        $comment = $comment->load('user:id,name', 'message');

        // broadcast(new ChatCreated($comment));

        // Optional: log activity
        $message->activities()->create([
            'user_id' => $user->id,
            'action' => 'added a comment',
            'assignee_id' => null,
        ]);

        // -----------------------------
        // Notification logic
        // -----------------------------

        // Merge recipients: message creator + assignees (excluding current user)
        $assignees = $message->assignees()->pluck('users.id')->toArray(); // array of user IDs
        $creatorId = $message->creator_id;

        $userIds = collect($assignees)
            ->push($creatorId)
            ->filter(fn($id) => $id !== $user->id) // exclude commenter
            ->unique()
            ->values()
            ->toArray();

        if (!empty($userIds)) {
            $notification = Notification::createWithRecipients(
                title: $user->name . " hat auf " . $message->title . " kommentiert",
                body: $comment->content,
                type: "comment_created",
                messageId: $message->id,
                creatorId: $user->id,
                userIds: $userIds
            );

            foreach ($notification->recipients as $recipient) {
                broadcast(new NotificationCreated(
                    recipient: $recipient,
                    notification: $notification
                ))->toOthers();

                $tokens = $recipient->user->pushTokens()->pluck('token')->filter()->toArray();

                ExpoPushService::send(
                    $tokens,
                    'Neue kommentar',
                    $user->name . ' hat auf "' . $message->title . '" kommentiert.',
                    [
                        'type' => 'chat',
                        'messageId' => $message->id,
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Kommentar erfolgreich hinzugefügt',
            'data' => $comment
        ], 201);
    }
}
