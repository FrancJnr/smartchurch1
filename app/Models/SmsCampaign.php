<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsCampaign extends Model
{
    use HasFactory;

    protected $fillable = ['created_by', 'audience', 'audience_label', 'group_id', 'district_id', 'message', 'status', 'matched_members', 'invalid_numbers', 'duplicate_numbers', 'queued_at'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsRecipient::class);
    }
}
