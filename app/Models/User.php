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

    // ── Relasi Sales ──
    public function leads(): HasMany      { return $this->hasMany(Lead::class, 'user_id'); }
    public function activities(): HasMany { return $this->hasMany(Activity::class, 'user_id'); }
    public function customers(): HasMany  { return $this->hasMany(Customer::class, 'user_id'); }

    // ── Role helpers ──
    public function isAdmin(): bool          { return $this->role === 'Admin'; }
    public function isSalesManager(): bool   { return $this->role === 'Sales Manager'; }
    public function isSalesExecutive(): bool { return $this->role === 'Sales Executive'; }

    public function canAccess(string $feature): bool
    {
        return match($feature) {
            'settings'        => $this->isAdmin(),
            'users'           => in_array($this->role, ['Admin', 'Sales Manager']),
            'reports'         => in_array($this->role, ['Admin', 'Sales Manager']),
            'analytics'       => in_array($this->role, ['Admin', 'Sales Manager']),
            'vendors'         => in_array($this->role, ['Admin', 'Sales Manager']),
            'delivery_orders' => in_array($this->role, ['Admin', 'Sales Manager']),
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

