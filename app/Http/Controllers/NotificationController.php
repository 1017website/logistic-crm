<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** AJAX: ambil notifikasi terbaru user yang login */
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'message'    => $n->message,
                'icon'       => $n->icon,
                'icon_color' => $n->icon_color,
                'url'        => $n->url,
                'is_read'    => $n->is_read,
                'time'       => $n->created_at->diffForHumans(),
                'date'       => $n->created_at->format('d M Y H:i'),
            ]);

        $unreadCount = Notification::where('user_id', auth()->id())
            ->where('is_read', false)->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /** AJAX: mark satu notifikasi sebagai read */
    public function markRead(Notification $notification)
    {
        if ($notification->user_id === auth()->id()) {
            $notification->update(['is_read' => true]);
        }
        return response()->json(['success' => true]);
    }

    /** AJAX: mark semua sebagai read */
    public function markAllRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'unread_count' => 0]);
    }

    /** AJAX: unread count saja (untuk polling) */
    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('is_read', false)->count();

        return response()->json(['unread_count' => $count]);
    }
}
