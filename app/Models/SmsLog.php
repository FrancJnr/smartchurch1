<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['giving_id', 'phone', 'message', 'status', 'provider_reference', 'error_message', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function giving()
    {
        return $this->belongsTo(Giving::class);
    }
}
