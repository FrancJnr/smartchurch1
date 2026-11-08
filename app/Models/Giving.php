<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Giving extends Model
{
    protected $fillable = ['member_id', 'giving_type_id', 'amount', 'payment_method', 'transaction_reference', 'external_reference', 'giving_date', 'recorded_by'];

    protected function casts(): array
    {
        return ['giving_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function givingType()
    {
        return $this->belongsTo(GivingType::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function smsLog()
    {
        return $this->belongsTo(SmsLog::class, 'sms_log_id');
    }
}
