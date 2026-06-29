<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ForumCommentResource;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Notifications\ForumReplyNotification;
use Illuminate\Http\Request;

class ForumCommentController extends Controller
{
    public function store(Request $request, ForumPost $forumPost)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'exists:forum_comments,id'],
        ]);

        $comment = $forumPost->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
        ]);

        $forumPost->increment('comments_count');

        if ($forumPost->user_id !== $request->user()->id) {
            $forumPost->user->notify(new ForumReplyNotification($comment));
        }

        return new ForumCommentResource($comment->load('user'));
    }

    public function toggleLike(Request $request, ForumComment $forumComment)
    {
        $existing = $forumComment->likes()->where('user_id', $request->user()->id)->first();

        if ($existing) {
            $existing->delete();
            $forumComment->decrement('likes_count');
        } else {
            $forumComment->likes()->create(['user_id' => $request->user()->id]);
            $forumComment->increment('likes_count');
        }

        return response()->json([
            'liked' => ! $existing,
            'likes' => $forumComment->fresh()->likes_count,
        ]);
    }

    public function markSolution(Request $request, ForumComment $forumComment)
    {
        $post = $forumComment->post;
        abort_unless($post->user_id === $request->user()->id, 403);

        $post->comments()->update(['is_solution' => false]);
        $forumComment->update(['is_solution' => true]);
        $post->update(['solved' => true]);

        return new ForumCommentResource($forumComment->fresh());
    }

    public function destroy(Request $request, ForumComment $forumComment)
    {
        abort_unless(
            $forumComment->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );

        $forumComment->post()->decrement('comments_count');
        $forumComment->delete();

        return response()->json(['message' => 'Komentar dihapus.']);
    }
}
