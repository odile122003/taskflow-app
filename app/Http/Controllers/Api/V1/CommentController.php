<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function index(Project $project, Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        return CommentResource::collection(
            $task->comments()->with('user')->latest()->paginate()
        );
    }

    public function store(StoreCommentRequest $request, Project $project, Task $task): CommentResource
    {
        $this->authorize('create', [Comment::class, $task]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return new CommentResource($comment->load('user'));
    }

    public function destroy(Project $project, Task $task, Comment $comment): Response
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
