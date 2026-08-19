<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('assignee_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('priority')->default('normal')->after('status');
            $table->date('due_date')->nullable()->after('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['priority', 'due_date']);
        });
    }
};
