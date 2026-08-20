<?php

namespace App\Jobs;

use App\Models\Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * On passe l'id de la pièce jointe, jamais le modèle lui-même : le job est
 * sérialisé et stocké tel quel dans la table `jobs` (potentiellement pendant
 * un moment avant qu'un worker le reprenne) — un modèle complet gonflerait
 * la charge utile, et ses données pourraient être périmées d'ici l'exécution.
 */
class GenerateThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $attachmentId,
    ) {}

    public function handle(): void
    {
        $attachment = Attachment::find($this->attachmentId);

        // Idempotent : si la pièce jointe a été supprimée entre le dispatch et
        // l'exécution, ou si la miniature existe déjà (rejeu après un échec
        // partiel), on ne refait pas le travail. Un job repris après un échec
        // réseau ne doit jamais produire un résultat différent d'un premier
        // essai réussi, ni planter sur un état déjà correct.
        if ($attachment === null || $attachment->thumbnail_path !== null) {
            return;
        }

        if (! Storage::exists($attachment->path)) {
            return;
        }

        $thumbnailPath = 'thumbnails/'.$attachment->attachable_id.'/'.basename($attachment->path);

        $image = ImageManager::gd()
            ->read(Storage::path($attachment->path))
            ->scaleDown(width: 300);

        Storage::put($thumbnailPath, (string) $image->encode());

        $attachment->update(['thumbnail_path' => $thumbnailPath]);
    }
}
