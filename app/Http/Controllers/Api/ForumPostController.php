<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ForumPostResource;
use App\Models\ForumBookmark;
use App\Models\ForumLike;
use App\Models\ForumPost;
use Illuminate\Http\Request;

class ForumPostController extends Controller
{
    public function index(Request $request)
    {
        $query = ForumPost::query()->with(['user', 'category']);

        if ($categoryId = $request->input('category_id')) {
            $query->where('forum_category_id', $categoryId);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'terbaru');
        match ($sort) {
            'trending' => $query->orderByDesc('likes_count')->orderByDesc('comments_count'),
            'belum_terjawab' => $query->where('solved', false)->latest(),
            default => $query->latest(),
        };

        if ($request->user()) {
            $query->with([
                'likes' => fn ($q) => $q->where('user_id', $request->user()->id),
                'bookmarks' => fn ($q) => $q->where('user_id', $request->user()->id),
            ]);
        }

        $posts = $query->paginate($request->integer('per_page', 10));

        return ForumPostResource::collection($posts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'forum_category_id' => ['required', 'exists:forum_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $post = $request->user()->forumPosts()->create($validated);

        return new ForumPostResource($post->load(['user', 'category']));
    }

    public function show(Request $request, ForumPost $forumPost)
    {
        $forumPost->increment('views_count');

        $forumPost->load([
            'user', 'category',
            'comments' => fn ($q) => $q->whereNull('parent_id')->with(['user', 'replies.user'])->oldest(),
        ]);

        if ($request->user()) {
            $forumPost->load([
                'likes' => fn ($q) => $q->where('user_id', $request->user()->id),
                'bookmarks' => fn ($q) => $q->where('user_id', $request->user()->id),
            ]);
        }

        return new ForumPostResource($forumPost);
    }

    public function toggleLike(Request $request, ForumPost $forumPost)
    {
        $this->toggleLikeFor($request, $forumPost);

        return response()->json([
            'liked' => $forumPost->likes()->where('user_id', $request->user()->id)->exists(),
            'likes' => $forumPost->fresh()->likes_count,
        ]);
    }

    public function markSolved(Request $request, ForumPost $forumPost)
    {
        abort_unless($forumPost->user_id === $request->user()->id, 403);

        $forumPost->update(['solved' => true]);

        return new ForumPostResource($forumPost);
    }

    public function toggleBookmark(Request $request, ForumPost $forumPost)
    {
        $existing = $forumPost->bookmarks()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $bookmarked = false;
        } else {
            $forumPost->bookmarks()->create(['user_id' => $request->user()->id]);
            $bookmarked = true;
        }

        return response()->json(['bookmarked' => $bookmarked]);
    }

    public function update(Request $request, ForumPost $forumPost)
    {
        abort_unless($forumPost->user_id === $request->user()->id, 403);
        abort_unless($forumPost->created_at->greaterThanOrEqualTo(now()->subMinutes(5)), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $forumPost->update($validated);

        return new ForumPostResource($forumPost->fresh()->load(['user', 'category']));
    }

    public function destroy(Request $request, ForumPost $forumPost)
    {
        abort_unless($forumPost->user_id === $request->user()->id, 403);

        $forumPost->delete();

        return response()->json(['deleted' => true]);
    }

    protected function toggleLikeFor(Request $request, ForumPost $forumPost): void
    {
        $existing = $forumPost->likes()->where('user_id', $request->user()->id)->first();

        if ($existing) {
            $existing->delete();
            $forumPost->decrement('likes_count');
        } else {
            $forumPost->likes()->create(['user_id' => $request->user()->id]);
            $forumPost->increment('likes_count');
        }
    }
}
