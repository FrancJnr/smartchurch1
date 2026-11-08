<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Member extends Model
{
    protected $fillable = ['ekanisa_number', 'name', 'phone', 'status', 'district_id', 'group_id'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function givings()
    {
        return $this->hasMany(Giving::class);
    }
}
