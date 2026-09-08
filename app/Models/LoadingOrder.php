<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadingOrder extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['letter_date' => 'date', 'company' => 'array'];

    public function fingerprint(): string
    {
        $attributes = $this->getAttributes();
        unset($attributes['updated_at'], $attributes['created_at'], $attributes['deleted_at']);
        ksort($attributes);
        return hash('sha256', json_encode($attributes));
    }
}
