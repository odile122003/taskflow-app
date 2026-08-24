<?php

namespace App\Services\Attachments;

use App\Contracts\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Implémentation par défaut : délègue au disque Laravel configuré
 * (FILESYSTEM_DISK). Aucune mention de "local" ou "s3" ici — c'est déjà le
 * rôle de config/filesystems.php, cette classe ne fait qu'exposer les trois
 * opérations dont AttachmentController a besoin, sous le vocabulaire du
 * contrat plutôt que celui, plus large, de la façade Storage.
 */
final class DiskAttachmentStorage implements AttachmentStorage
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory);
    }

    public function delete(string $path): void
    {
        Storage::delete($path);
    }

    public function download(string $path, string $downloadName): StreamedResponse
    {
        return Storage::download($path, $downloadName);
    }
}
