<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'position',
        'avatar', 'role', 'status', 'target',
    ];

    protected $hidden = ['password', 'remember_token'];
    protected $casts  = ['password' => 'hashed'];

    /** Semua role yang valid di sistem. */
    public const ROLES = [
        'Admin',
        'Sales Manager',
        'Sales Executive',
        'Sales Admin',
        'Transport Planner',
        'Finance',
    ];

    // ── Relasi Sales ──
    public function leads(): HasMany      { return $this->hasMany(Lead::class, 'user_id'); }
    public function activities(): HasMany { return $this->hasMany(Activity::class, 'user_id'); }
    public function customers(): HasMany  { return $this->hasMany(Customer::class, 'user_id'); }

    // ── Role helpers ──
    public function isAdmin(): bool            { return $this->role === 'Admin'; }
    public function isSalesManager(): bool     { return $this->role === 'Sales Manager'; }
    public function isSalesExecutive(): bool   { return $this->role === 'Sales Executive'; }
    public function isSalesAdmin(): bool       { return $this->role === 'Sales Admin'; }
    public function isTransportPlanner(): bool { return $this->role === 'Transport Planner'; }
    public function isFinance(): bool          { return $this->role === 'Finance'; }

    public function canAccess(string $feature): bool
    {
        return match($feature) {
            'settings'        => $this->isAdmin(),
            'users'           => in_array($this->role, ['Admin', 'Sales Manager']),
            'reports'         => in_array($this->role, ['Admin', 'Sales Manager']),
            'analytics'       => in_array($this->role, ['Admin', 'Sales Manager']),
            'sales_activity'  => !in_array($this->role, ['Sales Admin', 'Finance']),
            'customers'       => in_array($this->role, self::ROLES),
            'calendar'        => in_array($this->role, self::ROLES),
            'vendors'         => in_array($this->role, [
                'Admin', 'Sales Manager', 'Sales Admin', 'Transport Planner', 'Finance',
            ]),
            'service_types'   => in_array($this->role, ['Admin', 'Sales Manager', 'Transport Planner']),
            'quotations'      => in_array($this->role, ['Admin', 'Sales Manager', 'Sales Executive', 'Sales Admin']),

            // Request DO: dibuat sales, dilihat semua peran terkait alur.
            'request_orders'  => in_array($this->role, [
                'Admin', 'Sales Manager', 'Sales Executive', 'Sales Admin', 'Transport Planner', 'Finance',
            ]),
            // Verifikasi data request DO
            'verify_request'  => in_array($this->role, ['Admin', 'Sales Admin']),
            // Penugasan armada/vendor
            'dispatch'        => in_array($this->role, ['Admin', 'Transport Planner']),
            // Approval penugasan
            'approve_assign'  => in_array($this->role, ['Admin', 'Sales Manager']),
            // DO final + alur lapangan + tutup DO
            'delivery_orders' => in_array($this->role, ['Admin', 'Sales Manager', 'Sales Admin', 'Transport Planner', 'Finance']),
            'pod_field'       => in_array($this->role, ['Admin', 'Sales Admin']),
            // Finance: invoice & payment
            'finance'         => in_array($this->role, ['Admin', 'Finance']),
            'request_item_pricing' => in_array($this->role, ['Admin', 'Finance']),
            'invoices'        => in_array($this->role, ['Admin', 'Finance', 'Sales Manager']),
            'pekerjaan'       => in_array($this->role, ['Admin', 'Sales Manager', 'Transport Planner']),
            'job_details'     => in_array($this->role, ['Admin', 'Finance']),
            'approve_do'      => in_array($this->role, ['Admin', 'Sales Admin']),
            'logistic_reports'=> in_array($this->role, ['Admin', 'Sales Manager', 'Finance']),
            default           => true,
        };
    }

    public function getAvatarInitialsAttribute(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return 'US';
        }

        $parts = preg_split('/\s+/', $name);
        $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
        $second = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : strtoupper(substr($parts[0] ?? 'S', 1, 1));

        return trim($first . $second) ?: 'US';
    }
}
