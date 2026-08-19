<?php

namespace App\DataTransferObjects;

/**
 * Frontière entre la couche HTTP (StoreTaskRequest, tableau brut validé) et le
 * reste de l'application : au lieu de faire circuler un array (clés magiques,
 * aucune garantie de type), on construit une fois cet objet et tout le monde
 * en aval sait exactement ce qu'il contient. L'extraction vers une vraie classe
 * Action/Service qui consommerait ce DTO viendra au Module 11.
 */
final readonly class CreateTaskData
{
    /**
     * @param  array<int, int>  $tagIds
     */
    public function __construct(
        public string $title,
        public ?string $priority,
        public ?string $dueDate,
        public ?int $assigneeId,
        public array $tagIds,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            title: $validated['title'],
            priority: $validated['priority'] ?? null,
            dueDate: $validated['due_date'] ?? null,
            assigneeId: $validated['assignee_id'] ?? null,
            tagIds: $validated['tags'] ?? [],
        );
    }
}
