<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Scopes\TeamScope;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Une ligne = un job. Si une ligne du CSV est invalide, ce job échoue seul —
 * il ne fait pas échouer les autres lignes du batch, et Bus::batch() les
 * compte séparément (total_jobs / pending_jobs / failed_jobs).
 */
class ImportTaskRow implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, string|null>  $row
     */
    public function __construct(
        public int $projectId,
        public array $row,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $validator = Validator::make($this->row, [
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Le scope TeamScope filtre par l'équipe *courante* de la personne qui
        // fait la requête HTTP — hors de propos ici, un job tourne sans contexte
        // web. Le projet est déjà connu et fixé au moment du dispatch (import.store).
        $project = Project::withoutGlobalScope(TeamScope::class)->findOrFail($this->projectId);

        // Idempotent : un rejeu du batch (échec réseau, worker relancé) ne doit
        // pas dupliquer les tâches déjà importées avec succès la première fois.
        $project->tasks()->firstOrCreate(
            ['title' => $this->row['title']],
            [
                'priority' => $this->row['priority'] ?: 'normal',
                'due_date' => $this->row['due_date'] ?: null,
            ]
        );
    }
}
