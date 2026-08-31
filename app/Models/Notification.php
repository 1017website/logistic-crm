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

    /** Kirim ke semua user aktif pada role tertentu. */
    public static function sendToRoles(array $roles, string $type, string $title, string $message, ?string $url = null): void
    {
        User::whereIn('role', array_values(array_unique($roles)))
            ->where('status', 'Active')
            ->get()
            ->each(fn(User $user) => self::send($user->id, $type, $title, $message, $url));
    }

    /** Broadcast ke semua user Admin + Manager. */
    public static function broadcast(string $type, string $title, string $message, ?string $url = null): void
    {
        self::sendToRoles(['Super Admin', 'Admin', 'Sales Manager'], $type, $title, $message, $url);
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
            'delete_request'=> null,
            'request_do_pricing'=> null,
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
            'delete_request' => 'trash-alt',
            'request_do_pricing' => 'file-invoice-dollar',
            'invoice_due_soon'   => 'clock',
            'invoice_due_today'  => 'calendar-day',
            'invoice_overdue'    => 'file-invoice-dollar',
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
            'delete_request' => '#ef4444',
            'request_do_pricing' => '#f59e0b',
            'invoice_due_soon'   => '#f59e0b',
            'invoice_due_today'  => '#f97316',
            'invoice_overdue'    => '#dc2626',
            default          => '#3b82f6',
        };
    }
}
