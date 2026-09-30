<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient',
        'template_name',
        'language',
        'parameters',
        'meta_message_id',
        'status',
        'meta_response',
        'sent_at',
    ];

    protected $casts = [
        'parameters' => 'json',
        'meta_response' => 'json',
        'sent_at' => 'datetime',
    ];
}
