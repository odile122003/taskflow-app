<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['team_id', 'name', 'slug', 'color', 'is_archived', 'is_favorite'];

    protected $casts = [
        'is_archived' => 'boolean',
        'is_favorite' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
