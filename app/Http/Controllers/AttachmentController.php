<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Storage::disk() sans argument = le disque par défaut (FILESYSTEM_DISK).
     * Aucune méthode de ce contrôleur ne nomme "local" ou "s3" : changer le
     * disque par défaut dans .env suffit à tout déplacer, sans toucher au code.
     */
    public function store(StoreAttachmentRequest $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $file = $request->file('file');
        $path = $file->store('attachments/'.$task->id);

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json($attachment, 201);
    }

    /**
     * Le fichier passe par le serveur (Storage::download) plutôt que par une
     * URL publique directe : ça fonctionne pareil sur un disque privé
     * (`local`) ou public (`s3`), et surtout ça passe par la policy — un
     * lien de fichier n'est jamais accessible à qui n'est pas de l'équipe.
     */
    public function download(Project $project, Task $task, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $task);
        $this->abortUnlessBelongsToTask($attachment, $task);

        return Storage::download($attachment->path, $attachment->original_name);
    }

    public function destroy(Project $project, Task $task, Attachment $attachment): Response
    {
        $this->authorize('update', $task);
        $this->abortUnlessBelongsToTask($attachment, $task);

        Storage::delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }

    private function abortUnlessBelongsToTask(Attachment $attachment, Task $task): void
    {
        abort_unless(
            $attachment->attachable_type === Task::class && $attachment->attachable_id === $task->id,
            404
        );
    }
}
