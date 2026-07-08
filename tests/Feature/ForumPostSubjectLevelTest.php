<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumPostSubjectLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_forum_posts_can_store_and_filter_by_subject_and_level(): void
    {
        $user = User::factory()->create([
            'name' => 'Siswa Demo',
            'role' => 'siswa',
            'status' => 'active',
        ]);

        $category = ForumCategory::create([
            'name' => 'Umum',
            'slug' => 'umum',
        ]);

        $subject = Subject::create([
            'name' => 'Matematika SD',
            'slug' => 'matematika-sd',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/forum/posts', [
            'forum_category_id' => $category->id,
            'title' => 'Diskusi matematika',
            'body' => 'Butuh bantuan belajar',
            'subject_id' => $subject->id,
            'education_level' => 'SD',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject.id', $subject->id)
            ->assertJsonPath('data.education_level', 'SD');

        $filterResponse = $this->getJson('/api/forum/posts?subject_id=' . $subject->id . '&education_level=SD');

        $filterResponse->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
