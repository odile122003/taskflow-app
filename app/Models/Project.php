<?php

namespace App\Models;

use App\Casts\HexColor;
use App\Models\Scopes\TeamScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['team_id', 'name', 'slug', 'color', 'is_archived', 'is_favorite'];

    protected $casts = [
        'is_archived' => 'boolean',
        'is_favorite' => 'boolean',
        'color' => HexColor::class,
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Scope global : n'agit que si un CurrentTeam a été positionné (voir
        // AppServiceProvider). Sans ça, no-op — on ne casse rien avant le Module 6.
        static::addGlobalScope(new TeamScope);

        // archived_at n'est jamais dans $fillable : géré uniquement ici, jamais
        // depuis un formulaire — sinon rien n'empêcherait un client d'envoyer
        // une date arbitraire pour échapper au nettoyage automatique (Module 8).
        static::updating(function (Project $project) {
            if (! $project->isDirty('is_archived')) {
                return;
            }

            $project->archived_at = $project->is_archived ? now() : null;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Scope local : usage explicite, indépendant du contexte "équipe courante".
     * Project::forTeam($team)->get()
     */
    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
