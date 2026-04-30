<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'status', 'target', 'phone',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['password' => 'hashed'];

    // Role helpers
    public function isAdmin(): bool          { return $this->role === 'Admin'; }
    public function isSalesManager(): bool   { return $this->role === 'Sales Manager'; }
    public function isSalesExecutive(): bool { return $this->role === 'Sales Executive'; }

    public function canAccess(string $feature): bool
    {
        return match($feature) {
            'settings' => $this->isAdmin(),
            'users'    => in_array($this->role, ['Admin', 'Sales Manager']),
            'reports'  => in_array($this->role, ['Admin', 'Sales Manager']),
            'analytics'=> in_array($this->role, ['Admin', 'Sales Manager']),
            default    => true, // semua role bisa akses
        };
    }

    public function getAvatarInitialsAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }
}
