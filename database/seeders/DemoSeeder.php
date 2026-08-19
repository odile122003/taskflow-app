<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // La création de l'équipe et de ses membres doit réussir ensemble ou pas du
        // tout : si l'insertion des lignes team_user échoue à mi-chemin, on ne veut
        // pas d'une équipe sans membres en base.
        $team = DB::transaction(function () {
            $team = Team::factory()->create();

            $owner = User::factory()->create(['name' => 'Alice Owner', 'email' => 'alice@taskflow.test']);
            $admin = User::factory()->create(['name' => 'Bob Admin', 'email' => 'bob@taskflow.test']);
            $members = User::factory()->count(3)->create();

            $now = now();

            DB::table('team_user')->insert([
                ['team_id' => $team->id, 'user_id' => $owner->id, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now],
                ['team_id' => $team->id, 'user_id' => $admin->id, 'role' => 'admin', 'created_at' => $now, 'updated_at' => $now],
                ...$members->map(fn (User $member) => [
                    'team_id' => $team->id,
                    'user_id' => $member->id,
                    'role' => 'member',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            ]);

            return $team;
        });

        $memberIds = DB::table('team_user')->where('team_id', $team->id)->pluck('user_id');

        $tags = Tag::factory()->count(5)->create();

        $projects = Project::factory()->count(3)->create(['team_id' => $team->id]);
        $projects->push(Project::factory()->archived()->create(['team_id' => $team->id]));

        foreach ($projects as $project) {
            $tasks = Task::factory()
                ->count(8)
                ->for($project)
                ->sequence(
                    ['status' => 'todo'],
                    ['status' => 'in_progress'],
                    ['status' => 'done'],
                )
                ->create();

            foreach ($tasks->random(min(4, $tasks->count())) as $task) {
                $task->update(['assignee_id' => $memberIds->random()]);

                Comment::factory()->on($task)->create(['user_id' => $memberIds->random()]);

                DB::table('taggables')->insert([
                    'tag_id' => $tags->random()->id,
                    'taggable_type' => Task::class,
                    'taggable_id' => $task->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Activity::factory()->create([
                    'causer_id' => $memberIds->random(),
                    'subject_type' => Task::class,
                    'subject_id' => $task->id,
                    'description' => 'a changé le statut de la tâche « '.$task->title.' »',
                ]);
            }

            Attachment::factory()->create([
                'user_id' => $memberIds->random(),
                'attachable_type' => Task::class,
                'attachable_id' => $tasks->first()->id,
            ]);
        }
    }
}
