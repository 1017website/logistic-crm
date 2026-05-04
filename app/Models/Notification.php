<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message',
        'icon', 'icon_color', 'url', 'is_read',
    ];

    protected $casts = ['is_read' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Static helpers ──

    /** Kirim notifikasi ke user tertentu */
    public static function send(int $userId, string $type, string $title, string $message, ?string $url = null): void
    {
        // Cek apakah setting notif type ini aktif
        $settingKey = self::settingKey($type);
        if ($settingKey && Setting::get($settingKey, '1') !== '1') return;

        static::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'icon'       => self::icon($type),
            'icon_color' => self::color($type),
            'url'        => $url,
            'is_read'    => false,
        ]);
    }

    /** Broadcast ke semua user Admin + Manager */
    public static function broadcast(string $type, string $title, string $message, ?string $url = null): void
    {
        $settingKey = self::settingKey($type);
        if ($settingKey && Setting::get($settingKey, '1') !== '1') return;

        $managers = User::whereIn('role', ['Admin', 'Sales Manager'])->where('status', 'Active')->get();
        foreach ($managers as $user) {
            static::create([
                'user_id'    => $user->id,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'icon'       => self::icon($type),
                'icon_color' => self::color($type),
                'url'        => $url,
                'is_read'    => false,
            ]);
        }
    }

    /** Kirim ke semua user aktif */
    public static function sendAll(string $type, string $title, string $message, ?string $url = null): void
    {
        $settingKey = self::settingKey($type);
        if ($settingKey && Setting::get($settingKey, '1') !== '1') return;

        $users = User::where('status', 'Active')->get();
        foreach ($users as $user) {
            static::create([
                'user_id'    => $user->id,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'icon'       => self::icon($type),
                'icon_color' => self::color($type),
                'url'        => $url,
                'is_read'    => false,
            ]);
        }
    }

    private static function settingKey(string $type): ?string
    {
        return match($type) {
            'overdue'       => 'notif_overdue',
            'new_lead'      => 'notif_new_lead',
            'deal_won'      => 'notif_deal_won',
            'followup'      => 'notif_followup',
            'stage_change'  => 'notif_stage',
            'weekly'        => 'notif_weekly',
            'target_warning'=> 'notif_target',
            default         => null,
        };
    }

    private static function icon(string $type): string
    {
        return match($type) {
            'overdue'        => 'exclamation-circle',
            'new_lead'       => 'user-plus',
            'deal_won'       => 'trophy',
            'followup'       => 'clock',
            'stage_change'   => 'filter',
            'target_warning' => 'chart-line',
            'weekly'         => 'file-alt',
            default          => 'bell',
        };
    }

    private static function color(string $type): string
    {
        return match($type) {
            'overdue'        => '#ef4444',
            'new_lead'       => '#3b82f6',
            'deal_won'       => '#10b981',
            'followup'       => '#f59e0b',
            'stage_change'   => '#8b5cf6',
            'target_warning' => '#f97316',
            'weekly'         => '#6b7280',
            default          => '#3b82f6',
        };
    }
}
