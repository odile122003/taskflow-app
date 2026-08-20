<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Models\Scopes\TeamScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['project_id', 'assignee_id', 'parent_id', 'title', 'status', 'priority', 'due_date'];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'status' => TaskStatus::class,
    ];

    /**
     * Accessor moderne (Attribute::make) : $task->is_overdue, calculé, jamais stocké.
     */
    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due_date !== null
                && $this->due_date->isPast()
                && $this->status !== TaskStatus::Done,
        );
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Même relation, mais sans le scope global TeamScope de Project. Réservée
     * aux policies : elles s'exécutent après que le middleware SetCurrentTeam
     * a positionné l'équipe *courante*, donc un `$task->project` scopé sur
     * une tâche d'une AUTRE équipe renverrait `null` (au lieu du vrai projet)
     * — un crash au lieu d'un refus propre. L'autorisation doit toujours voir
     * le véritable projet propriétaire, jamais un projet filtré par le
     * contexte de la personne qui demande l'accès.
     *
     * @return BelongsTo<Project, $this>
     */
    public function projectForAuthorization(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id')->withoutGlobalScope(TeamScope::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
