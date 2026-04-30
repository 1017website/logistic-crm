<?php
// app/Models/SalesUser.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesUser extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'position', 'avatar', 'role', 'status', 'target'];

    public function leads(): HasMany { return $this->hasMany(Lead::class); }
    public function activities(): HasMany { return $this->hasMany(Activity::class); }
    public function customers(): HasMany { return $this->hasMany(Customer::class); }

    public function getAvatarInitialsAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }
}
