<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $casts = [
        'conversation_id' => 'integer',
        'sender_id' => 'integer',
        'is_seen' => 'integer',
        'file' => 'array'
    ];

    public function sender()
    {
        return $this->belongsTo(UserInfo::class, 'sender_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
