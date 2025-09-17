<?php

/**
 * Example: Dashboard with Real-time Updates
 * 
 * This example shows how to create a dashboard that receives real-time updates
 * about various system events, notifications, and statistics.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LaravelNotify\Events\NotificationEvent;
use LaravelNotify\Events\NewMessageEvent;

class DashboardExampleController extends Controller
{
    /**
     * Display the dashboard
     */
    public function index()
    {
        $user = auth()->user();
        
        // Get initial dashboard data
        $stats = [
            'total_orders' => \App\Models\Order::count(),
            'pending_orders' => \App\Models\Order::where('status', 'pending')->count(),
            'total_users' => \App\Models\User::count(),
            'revenue_today' => \App\Models\Order::whereDate('created_at', today())
                ->where('status', 'completed')
                ->sum('total'),
        ];
        
        $recentOrders = \App\Models\Order::with('user')
            ->latest()
            ->limit(10)
            ->get();
        
        $recentUsers = \App\Models\User::latest()
            ->limit(5)
            ->get();
        
        return view('dashboard.index', compact('stats', 'recentOrders', 'recentUsers'));
    }

    /**
     * Send a system-wide announcement
     */
    public function sendAnnouncement(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:500',
            'type' => 'required|in:info,warning,success,error',
            'target' => 'required|in:all,admins,users',
        ]);

        $announcement = [
            'type' => 'announcement',
            'level' => $request->input('type'),
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'timestamp' => now()->toISOString(),
            'dismissible' => true,
            'action_url' => $request->input('action_url'),
        ];

        $target = $request->input('target');

        switch ($target) {
            case 'all':
                // Send to public channel
                broadcast(new NewMessageEvent($announcement, null, 'announcements'));
                break;
                
            case 'admins':
                // Send to admin users only
                $adminUsers = \App\Models\User::whereHas('roles', function($q) {
                    $q->where('name', 'admin');
                })->get();
                
                foreach ($adminUsers as $admin) {
                    broadcast(new NotificationEvent($announcement, $admin->id));
                }
                break;
                
            case 'users':
                // Send to regular users
                $regularUsers = \App\Models\User::whereDoesntHave('roles', function($q) {
                    $q->where('name', 'admin');
                })->get();
                
                foreach ($regularUsers as $user) {
                    broadcast(new NotificationEvent($announcement, $user->id));
                }
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Announcement sent successfully',
            'recipients' => $target
        ]);
    }

    /**
     * Update dashboard statistics in real-time
     */
    public function updateStats()
    {
        $stats = [
            'total_orders' => \App\Models\Order::count(),
            'pending_orders' => \App\Models\Order::where('status', 'pending')->count(),
            'total_users' => \App\Models\User::count(),
            'revenue_today' => \App\Models\Order::whereDate('created_at', today())
                ->where('status', 'completed')
                ->sum('total'),
            'updated_at' => now()->toISOString(),
        ];

        // Broadcast updated stats to dashboard channel
        broadcast(new NewMessageEvent([
            'type' => 'stats_update',
            'data' => $stats
        ], null, 'dashboard.stats'));

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Send real-time alert for critical events
     */
    public function sendCriticalAlert(Request $request)
    {
        $request->validate([
            'event_type' => 'required|string',
            'message' => 'required|string',
            'severity' => 'required|in:low,medium,high,critical',
            'affected_systems' => 'array',
        ]);

        $alert = [
            'type' => 'critical_alert',
            'event_type' => $request->input('event_type'),
            'message' => $request->input('message'),
            'severity' => $request->input('severity'),
            'affected_systems' => $request->input('affected_systems', []),
            'timestamp' => now()->toISOString(),
            'alert_id' => \Str::uuid(),
        ];

        // Send to admin dashboard and monitoring systems
        broadcast(new NewMessageEvent($alert, null, 'admin.alerts'));
        
        // Also send as direct notifications to all admins
        $admins = \App\Models\User::whereHas('roles', function($q) {
            $q->where('name', 'admin');
        })->get();

        foreach ($admins as $admin) {
            broadcast(new NotificationEvent([
                'type' => 'alert',
                'title' => 'Critical System Alert',
                'message' => $alert['message'],
                'level' => 'error',
                'data' => $alert,
            ], $admin->id));
        }

        return response()->json([
            'success' => true,
            'alert_id' => $alert['alert_id']
        ]);
    }

    /**
     * Handle user activity events
     */
    public function trackUserActivity(Request $request)
    {
        $user = auth()->user();
        $activity = $request->input('activity');
        $metadata = $request->input('metadata', []);

        $activityData = [
            'type' => 'user_activity',
            'user_id' => $user->id,
            'user_name' => $user->name,
            'activity' => $activity,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Send to admin monitoring channel
        broadcast(new NewMessageEvent($activityData, null, 'admin.user_activity'));

        // Log activity
        \Log::info('User activity tracked', $activityData);

        return response()->json(['success' => true]);
    }

    /**
     * Get WebSocket authentication token for dashboard
     */
    public function getDashboardToken()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $authHandler = new \LaravelNotify\Server\AuthenticationHandler(
            config('realtime.auth')
        );

        $token = $authHandler->generateToken([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);

        return response()->json([
            'token' => $token,
            'channels' => $this->getUserDashboardChannels($user),
        ]);
    }

    /**
     * Get channels a user should subscribe to for dashboard updates
     */
    private function getUserDashboardChannels($user)
    {
        $channels = [
            'announcements',
            'user.' . $user->id,
        ];

        if ($user->hasRole('admin')) {
            $channels = array_merge($channels, [
                'admin.alerts',
                'admin.user_activity',
                'dashboard.stats',
            ]);
        }

        if ($user->hasRole('manager')) {
            $channels[] = 'manager.reports';
        }

        return $channels;
    }
}