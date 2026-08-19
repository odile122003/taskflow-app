<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attachable_type',
        'attachable_id',
        'path',
        'original_name',
        'size',
        'mime_type',
    ];
}
