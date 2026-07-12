<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'type', 'amount', 'sender_number', 'receiver_number', 'admin_bkash_number', 'status'])]
class Transaction extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}