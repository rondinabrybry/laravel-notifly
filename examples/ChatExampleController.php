<?php

/**
 * Example: Chat Implementation
 * 
 * This example shows how to implement a real-time chat system.
 */

namespace App\Http\Controllers;

use App\Events\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatExampleController extends Controller
{
    /**
     * Join a chat room
     */
    public function joinRoom(Request $request)
    {
        $roomId = $request->input('room_id');
        $user = Auth::user();

        // Verify user can access this room
        $room = ChatRoom::findOrFail($roomId);
        
        if (!$room->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        return response()->json([
            'room' => $room,
            'channel' => "chat.room.{$roomId}",
            'websocket_token' => $this->generateWebSocketToken($user),
        ]);
    }

    /**
     * Send a message to a chat room
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'room_id' => 'required|exists:chat_rooms,id',
            'message' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        $roomId = $request->input('room_id');
        $messageContent = $request->input('message');

        // Save message to database
        $message = Message::create([
            'user_id' => $user->id,
            'chat_room_id' => $roomId,
            'content' => $messageContent,
        ]);

        // Broadcast to all users in the room
        broadcast(new ChatMessage($message, $user, $roomId));

        return response()->json([
            'success' => true,
            'message' => $message->load('user'),
        ]);
    }

    /**
     * Get chat history for a room
     */
    public function getChatHistory(Request $request)
    {
        $roomId = $request->input('room_id');
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 50);

        $messages = Message::where('chat_room_id', $roomId)
            ->with('user:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'total_pages' => $messages->lastPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Handle user typing indicators
     */
    public function userTyping(Request $request)
    {
        $roomId = $request->input('room_id');
        $user = Auth::user();
        $isTyping = $request->input('is_typing', true);

        // Broadcast typing indicator to room (excluding the sender)
        $typingData = [
            'type' => 'typing',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'is_typing' => $isTyping,
            'room_id' => $roomId,
        ];

        broadcast(new \LaravelNotify\Events\NewMessageEvent(
            $typingData,
            null,
            "chat.room.{$roomId}"
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Get online users in a room
     */
    public function getOnlineUsers(Request $request)
    {
        $roomId = $request->input('room_id');
        
        // This would require additional WebSocket server modifications
        // to track online users per room
        
        return response()->json([
            'online_users' => [], // Implement based on your needs
            'count' => 0,
        ]);
    }

    /**
     * Generate WebSocket authentication token
     */
    private function generateWebSocketToken($user)
    {
        $authHandler = new \LaravelNotify\Server\AuthenticationHandler(
            config('realtime.auth')
        );

        return $authHandler->generateToken([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles ?? [],
        ]);
    }
}

/**
 * Example Chat Event
 */
class ChatMessage implements \Illuminate\Contracts\Broadcasting\ShouldBroadcast
{
    use \Illuminate\Foundation\Events\Dispatchable,
        \Illuminate\Broadcasting\InteractsWithSockets,
        \Illuminate\Queue\SerializesModels;

    public $message;
    public $user;
    public $roomId;

    public function __construct($message, $user, $roomId)
    {
        $this->message = $message;
        $this->user = $user;
        $this->roomId = $roomId;
    }

    public function broadcastOn()
    {
        return [
            new \Illuminate\Broadcasting\PresenceChannel("chat.room.{$this->roomId}")
        ];
    }

    public function broadcastAs()
    {
        return 'chat.message';
    }

    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'created_at' => $this->message->created_at->toISOString(),
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar' => $this->user->avatar ?? null,
                ],
            ],
            'room_id' => $this->roomId,
        ];
    }
}