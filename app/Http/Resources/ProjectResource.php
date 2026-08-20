<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'is_archived' => $this->is_archived,
            'archived_at' => $this->archived_at,
            // whenCounted : ce champ n'apparaît que si le contrôleur a fait
            // ->withCount('tasks') — sinon la clé est absente de la réponse
            // plutôt que null, pas de requête N+1 cachée pour la produire.
            'tasks_count' => $this->whenCounted('tasks'),
            'links' => [
                'self' => route('api.v1.projects.show', $this->resource),
                'board' => route('projects.board', $this->resource),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
