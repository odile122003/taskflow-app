<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = ['causer_id', 'subject_type', 'subject_id', 'description', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];
}
