<?php

namespace App\Http\Controllers;

use App\Contracts\AttachmentStorage;
use App\Http\Requests\StoreAttachmentRequest;
use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * AttachmentStorage $storage : le contrôleur ne sait pas s'il écrit sur
     * le disque local, sur S3, ou nulle part (tests). Voir App\Contracts\AttachmentStorage.
     */
    public function store(StoreAttachmentRequest $request, Project $project, Task $task, AttachmentStorage $storage): JsonResponse
    {
        $this->authorize('update', $task);

        $file = $request->file('file');
        $path = $storage->store($file, 'attachments/'.$task->id);

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        // La génération de miniature ne bloque jamais la réponse : redimensionner
        // une image est un travail CPU non négligeable, exécuté en tâche de fond.
        if (str_starts_with($attachment->mime_type, 'image/')) {
            GenerateThumbnail::dispatch($attachment->id);
        }

        return response()->json($attachment, 201);
    }

    /**
     * Le fichier passe par le serveur (Storage::download) plutôt que par une
     * URL publique directe : ça fonctionne pareil sur un disque privé
     * (`local`) ou public (`s3`), et surtout ça passe par la policy — un
     * lien de fichier n'est jamais accessible à qui n'est pas de l'équipe.
     */
    public function download(Project $project, Task $task, Attachment $attachment, AttachmentStorage $storage): StreamedResponse
    {
        $this->authorize('view', $task);
        $this->abortUnlessBelongsToTask($attachment, $task);

        return $storage->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Project $project, Task $task, Attachment $attachment, AttachmentStorage $storage): Response
    {
        $this->authorize('update', $task);
        $this->abortUnlessBelongsToTask($attachment, $task);

        $storage->delete($attachment->path);
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
