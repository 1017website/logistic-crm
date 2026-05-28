<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'lead_id','customer_id','user_id','sales_user_id','type','subject',
        'description','activity_at','status','next_follow_up','photo'
    ];

    protected $casts = ['activity_at' => 'datetime', 'next_follow_up' => 'date'];

    public function lead(): BelongsTo     { return $this->belongsTo(Lead::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function salesUser(): BelongsTo { return $this->belongsTo(User::class, 'sales_user_id'); }
}
