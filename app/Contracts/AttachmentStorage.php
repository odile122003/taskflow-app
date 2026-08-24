<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Frontière entre AttachmentController et le mécanisme de stockage réel.
 * Le contrôleur ne connaît que ces trois opérations, jamais un disque
 * Laravel ni un chemin absolu — voir DiskAttachmentStorage (production,
 * délègue au disque configuré) et InMemoryAttachmentStorage (tests, aucune
 * écriture réelle) pour les deux implémentations.
 */
interface AttachmentStorage
{
    public function store(UploadedFile $file, string $directory): string;

    public function delete(string $path): void;

    public function download(string $path, string $downloadName): StreamedResponse;
}
