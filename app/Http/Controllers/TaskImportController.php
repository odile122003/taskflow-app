<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskImportRequest;
use App\Jobs\ImportTaskRow;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

class TaskImportController extends Controller
{
    /**
     * Un job par ligne de CSV, regroupés dans un seul Bus::batch() : la
     * réponse HTTP ne dépend jamais du nombre de lignes, et le client peut
     * suivre la progression via show() pendant que les jobs s'exécutent.
     */
    public function store(StoreTaskImportRequest $request, Project $project): JsonResponse
    {
        $this->authorize('create', [Task::class, $project]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $line);
        }
        fclose($handle);

        abort_if($rows === [], 422, 'Le fichier CSV ne contient aucune ligne de données.');

        $jobs = collect($rows)->map(fn (array $row) => new ImportTaskRow($project->id, $row));

        $batch = Bus::batch($jobs)
            ->name("Import CSV — {$project->name}")
            ->dispatch();

        return response()->json(['batch_id' => $batch->id, 'total' => $batch->totalJobs], 202);
    }

    /**
     * Pas de vérification par équipe ici : l'id de batch est un UUID non
     * devinable, et cette route ne renvoie que des compteurs de progression,
     * jamais le contenu des tâches importées.
     */
    public function show(string $batch): JsonResponse
    {
        $found = Bus::findBatch($batch);

        abort_if($found === null, 404);

        return response()->json([
            'id' => $found->id,
            'total' => $found->totalJobs,
            'processed' => $found->processedJobs(),
            'failed' => $found->failedJobs,
            'progress' => $found->progress(),
            'finished' => $found->finished(),
        ]);
    }
}
