<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\TutorMaterialsChanged;
use App\Models\TutorMaterial;
use App\Models\TutorMaterialComment;
use App\Models\TutorMaterialReaction;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TutorMaterialController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        return $profile->materials()->with(['subject', 'tutorProfile'])->latest()->get()->map(fn ($m) => [
            'id' => $m->id,
            'title' => $m->title,
            'description' => $m->description,
            'comments_enabled' => (bool) $m->comments_enabled,
            'subject' => $m->subject?->name,
            'type' => $m->type,
            'levels' => array_values(array_filter((array) ($m->tutorProfile?->levels ?? []), fn ($value) => is_string($value) && trim($value) !== '')),
            'url' => $m->url,
            'file_url' => $m->file_path ? asset('storage/'.$m->file_path) : null,
            'thumbnail_url' => $m->thumbnail_path ? asset('storage/'.$m->thumbnail_path) : null,
            'views' => $m->views_count,
            'likes' => $m->likes_count,
            'dislikes' => $m->dislikes_count,
            'comments_count' => $m->comments_count,
            'created_at' => $m->created_at,
        ]);
    }

    public function publicIndex(Request $request)
    {
        $perPage = max(6, min(24, (int) $request->query('per_page', 12)));
        $materials = TutorMaterial::with(['subject', 'tutorProfile.user'])
            ->latest()
            ->paginate($perPage);

        return [
            'data' => $materials->getCollection()->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'description' => $m->description,
                'comments_enabled' => (bool) $m->comments_enabled,
                'subject' => $m->subject?->name,
                'type' => $m->type,
                'url' => $m->url,
                'file_url' => $m->file_path ? asset('storage/'.$m->file_path) : null,
                'thumbnail_url' => $m->thumbnail_path ? asset('storage/'.$m->thumbnail_path) : null,
                'views' => $m->views_count,
                'likes' => $m->likes_count,
                'dislikes' => $m->dislikes_count,
                'comments_count' => $m->comments_count,
                'created_at' => $m->created_at,
                'uploader' => $m->tutorProfile?->user?->name,
            ]),
            'current_page' => $materials->currentPage(),
            'last_page' => $materials->lastPage(),
            'per_page' => $materials->perPage(),
            'total' => $materials->total(),
        ];
    }

    public function show(Request $request, TutorMaterial $material)
    {
        $material->load(['subject', 'tutorProfile.user', 'comments.user']);

        $myReaction = null;
        if ($request->user()) {
            $myReaction = $material->reactions()->where('user_id', $request->user()->id)->value('type');
        }

        return [
            'id' => $material->id,
            'title' => $material->title,
            'description' => $material->description,
            'comments_enabled' => (bool) $material->comments_enabled,
            'subject' => $material->subject?->name,
            'type' => $material->type,
            'levels' => array_values(array_filter((array) ($material->tutorProfile?->levels ?? []), fn ($value) => is_string($value) && trim($value) !== '')),
            'url' => $material->url,
            'file_url' => $material->file_path ? asset('storage/'.$material->file_path) : null,
            'thumbnail_url' => $material->thumbnail_path ? asset('storage/'.$material->thumbnail_path) : null,
            'views' => $material->views_count,
            'likes' => $material->likes_count,
            'dislikes' => $material->dislikes_count,
            'comments_count' => $material->comments_count,
            'comments' => $material->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'author' => $comment->user?->name ?? 'Pengguna',
                'text' => $comment->body,
                'created_at' => $comment->created_at,
            ]),
            'created_at' => $material->created_at,
            'tutor_profile_id' => $material->tutor_profile_id,
            'uploader' => $material->tutorProfile?->user?->name,
            'tutor' => $material->tutorProfile ? [
                'id' => $material->tutorProfile->id,
                'name' => $material->tutorProfile->user?->name,
                'photo' => $material->tutorProfile->profile_photo_url ?? $material->tutorProfile->user?->avatar_url,
            ] : null,
            'my_reaction' => $myReaction,
        ];
    }

    public function incrementView(Request $request, TutorMaterial $material)
    {
        $material->increment('views_count');

        return ['views' => $material->fresh()->views_count];
    }

    public function like(Request $request, TutorMaterial $material)
    {
        return $this->toggleReaction($request, $material, 'like');
    }

    public function dislike(Request $request, TutorMaterial $material)
    {
        return $this->toggleReaction($request, $material, 'dislike');
    }

    protected function toggleReaction(Request $request, TutorMaterial $material, string $type)
    {
        $userId = $request->user()->id;
        $existing = $material->reactions()->where('user_id', $userId)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();
            $myReaction = null;
        } elseif ($existing) {
            $existing->update(['type' => $type]);
            $myReaction = $type;
        } else {
            TutorMaterialReaction::create([
                'tutor_material_id' => $material->id,
                'user_id' => $userId,
                'type' => $type,
            ]);
            $myReaction = $type;
        }

        $material->update([
            'likes_count' => $material->reactions()->where('type', 'like')->count(),
            'dislikes_count' => $material->reactions()->where('type', 'dislike')->count(),
        ]);

        return [
            'likes' => $material->likes_count,
            'dislikes' => $material->dislikes_count,
            'my_reaction' => $myReaction,
        ];
    }

    public function comment(Request $request, TutorMaterial $material)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        if (! $material->comments_enabled) {
            return response()->json(['message' => 'Komentar dinonaktifkan untuk materi ini.'], 403);
        }

        $comment = TutorMaterialComment::create([
            'tutor_material_id' => $material->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);
        $comment->load('user');

        $material->increment('comments_count');

        return response()->json([
            'id' => $comment->id,
            'author' => $comment->user?->name ?? 'Pengguna',
            'text' => $comment->body,
            'created_at' => $comment->created_at,
            'comments_count' => $material->fresh()->comments_count,
        ], 201);
    }

    public function store(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'type' => ['required', 'string', 'max:50', 'in:pdf,word,powerpoint,video,image,youtube'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'url' => ['nullable', 'url', 'max:2000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:512000', 'required_without:url'],
            'thumbnail' => ['nullable', 'image', 'max:10240'],
        ]);

        if ($request->hasFile('file')) {
            $validated['file_path'] = $request->file('file')->store('materials', 'public');
        }

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail_path'] = $request->file('thumbnail')->store('materials/thumbnails', 'public');
        }

        $material = $profile->materials()->create($validated);

        $fileUrl = $material->file_path ? asset('storage/'.$material->file_path) : null;
        // attach computed URL for broadcast
        $material->file_url = $fileUrl;

        try {
            event(new TutorMaterialsChanged('created', $material));
        } catch (BroadcastException $exception) {
            Log::warning('Broadcast failed for tutor material created event: ' . $exception->getMessage());
        }

        return response()->json([
            'id' => $material->id,
            'title' => $material->title,
            'description' => $material->description,
            'comments_enabled' => (bool) $material->comments_enabled,
            'subject' => $material->subject?->name,
            'type' => $material->type,
            'url' => $material->url,
            'file_url' => $fileUrl,
            'thumbnail_url' => $material->thumbnail_path ? asset('storage/'.$material->thumbnail_path) : null,
            'created_at' => $material->created_at,
        ], 201);
    }

    public function update(Request $request, int $material)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();
        $item = $profile->materials()->findOrFail($material);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'type' => ['required', 'string', 'max:50', 'in:pdf,word,powerpoint,video,image,youtube'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'url' => ['nullable', 'url', 'max:2000'],
            'file' => ['nullable', 'file', 'max:512000'],
            'thumbnail' => ['nullable', 'image', 'max:10240'],
        ]);

        if ($request->hasFile('file')) {
            if ($item->file_path) {
                Storage::disk('public')->delete($item->file_path);
            }
            $validated['file_path'] = $request->file('file')->store('materials', 'public');
        }

        if ($request->hasFile('thumbnail')) {
            if ($item->thumbnail_path) {
                Storage::disk('public')->delete($item->thumbnail_path);
            }
            $validated['thumbnail_path'] = $request->file('thumbnail')->store('materials/thumbnails', 'public');
        }

        $item->update($validated);

        $fileUrl = $item->file_path ? asset('storage/'.$item->file_path) : null;
        $item->file_url = $fileUrl;

        try {
            event(new TutorMaterialsChanged('updated', $item));
        } catch (BroadcastException $exception) {
            Log::warning('Broadcast failed for tutor material updated event: ' . $exception->getMessage());
        }

        return response()->json([
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'comments_enabled' => (bool) $item->comments_enabled,
            'subject' => $item->subject?->name,
            'type' => $item->type,
            'url' => $item->url,
            'file_url' => $fileUrl,
            'thumbnail_url' => $item->thumbnail_path ? asset('storage/'.$item->thumbnail_path) : null,
            'created_at' => $item->created_at,
        ]);
    }

    public function destroy(Request $request, int $material)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();
        $item = $profile->materials()->findOrFail($material);

        if ($item->file_path) {
            Storage::disk('public')->delete($item->file_path);
        }

        $payload = [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'subject' => $item->subject?->name,
            'file_url' => $item->file_path ? asset('storage/'.$item->file_path) : null,
            'created_at' => $item->created_at,
        ];

        // broadcast deletion before removing
        $item->file_url = $payload['file_url'];
        try {
            event(new TutorMaterialsChanged('deleted', $item));
        } catch (BroadcastException $exception) {
            Log::warning('Broadcast failed for tutor material deleted event: ' . $exception->getMessage());
        }

        $item->delete();

        return response()->json(['message' => 'Materi berhasil dihapus.']);
    }
}
