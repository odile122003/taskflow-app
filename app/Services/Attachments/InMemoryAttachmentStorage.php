<?php

namespace App\Services\Attachments;

use App\Contracts\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Implémentation de test : garde le contenu des fichiers dans un tableau PHP,
 * ne touche jamais le disque. Différence avec Storage::fake() (Module 10) :
 * Storage::fake() fait toujours passer le code par un vrai disque (temporaire,
 * sur le filesystem réel) — cette classe prouve que le contrôleur ne dépend
 * plus de rien de tel, en substituant l'implémentation entière via le
 * conteneur plutôt qu'en changeant de disque.
 */
final class InMemoryAttachmentStorage implements AttachmentStorage
{
    /** @var array<string, string> */
    private array $files = [];

    public function store(UploadedFile $file, string $directory): string
    {
        $path = rtrim($directory, '/').'/'.$file->hashName();

        $this->files[$path] = $file->get();

        return $path;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }

    public function download(string $path, string $downloadName): StreamedResponse
    {
        abort_unless(array_key_exists($path, $this->files), 404);

        $content = $this->files[$path];

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $downloadName);
    }

    public function has(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }
}
