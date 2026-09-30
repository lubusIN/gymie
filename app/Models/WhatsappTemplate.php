<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'language',
        'category',
        'body',
        'example_values',
        'meta_template_id',
        'status',
        'meta_response',
    ];

    protected $casts = [
        'example_values' => 'json',
        'meta_response' => 'json',
    ];
}
