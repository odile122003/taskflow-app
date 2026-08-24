<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * (project_id, status) existait déjà (Module 3) mais la liste des tâches
     * trie par created_at (defaultSort('-created_at'), Module 9) — sans
     * index sur cette paire, MySQL trie en mémoire (filesort) après avoir
     * localisé les lignes du projet. Voir CONCEPTS.md pour la mesure avant/après.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'created_at']);
        });
    }
};
