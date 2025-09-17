<?php

/**
 * Example: Basic Notification Usage
 * 
 * This example shows how to send a simple notification to a user.
 */

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use Illuminate\Http\Request;
use LaravelNotify\Events\NotificationEvent;

class NotificationExampleController extends Controller
{
    /**
     * Send a simple notification to a user
     */
    public function sendNotification(Request $request)
    {
        $userId = $request->input('user_id');
        $message = $request->input('message', 'Hello from Laravel Notify!');

        // Method 1: Using the NotificationEvent class
        $notification = [
            'type' => 'info',
            'title' => 'New Notification',
            'message' => $message,
            'action_url' => route('notifications.index'),
        ];

        broadcast(new NotificationEvent($notification, $userId));

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully'
        ]);
    }

    /**
     * Send notification to multiple users
     */
    public function sendBulkNotification(Request $request)
    {
        $userIds = $request->input('user_ids', []); // Array of user IDs
        $message = $request->input('message');

        foreach ($userIds as $userId) {
            $notification = [
                'type' => 'announcement',
                'title' => 'System Announcement',
                'message' => $message,
                'icon' => 'bell',
            ];

            broadcast(new NotificationEvent($notification, $userId));
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifications sent to ' . count($userIds) . ' users'
        ]);
    }

    /**
     * Send order status update
     */
    public function sendOrderUpdate(Request $request)
    {
        $orderId = $request->input('order_id');
        $status = $request->input('status');
        
        // Assuming you have an Order model
        $order = \App\Models\Order::findOrFail($orderId);
        $order->update(['status' => $status]);

        // Create a custom event for order updates
        broadcast(new OrderStatusUpdated($order));

        return response()->json([
            'success' => true,
            'message' => 'Order status updated and notification sent'
        ]);
    }

    /**
     * Send real-time alert to all connected users
     */
    public function sendSystemAlert(Request $request)
    {
        $message = $request->input('message');
        $level = $request->input('level', 'info'); // info, warning, error, success

        $alert = [
            'type' => 'system_alert',
            'level' => $level,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'dismissible' => true,
        ];

        // Broadcast to the public notifications channel
        broadcast(new \LaravelNotify\Events\NewMessageEvent($alert, null, 'notifications'));

        return response()->json([
            'success' => true,
            'message' => 'System alert broadcasted'
        ]);
    }
}