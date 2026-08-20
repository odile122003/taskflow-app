<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Types choisis pour TaskFlow (documents de travail) : images et PDF,
            // jamais d'exécutable ni de script. 10 Mo : suffisant pour une pièce
            // jointe de tâche, sans autoriser des vidéos qui saturent le disque.
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,docx,xlsx'],
        ];
    }
}
