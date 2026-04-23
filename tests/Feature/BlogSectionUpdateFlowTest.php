<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogSectionUpdateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_update_rejects_cover_image_and_remove_flag_together(): void
    {
        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user);

        Sanctum::actingAs($user);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/blogs/{$blog->id}", [
            '_method' => 'PUT',
            'title' => 'Updated title',
            'cover_image' => UploadedFile::fake()->create('new-cover.jpg', 100, 'image/jpeg'),
            'remove_cover_image' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cover_image', 'remove_cover_image']);

        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'title' => $blog->title,
        ]);
    }

    public function test_blog_update_updates_columns_and_replaces_cover_image_without_touching_sections(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user, [
            'cover_image_path' => 'cover_images/old-cover.jpg',
            'title' => 'Old title',
            'subtitle' => 'Old subtitle',
            'reading_time' => '3 min',
            'is_published' => false,
        ]);

        Storage::disk('public')->put('cover_images/old-cover.jpg', 'old-cover');
        $this->createSectionForBlog($blog, [
            'title' => 'Existing section',
            'content' => 'Existing content',
            'order' => 1,
        ]);

        Sanctum::actingAs($user);

        $response = $this->post("/api/blogs/{$blog->id}", [
            '_method' => 'PUT',
            'title' => 'New title',
            'subtitle' => 'New subtitle',
            'reading_time' => '5 min',
            'is_published' => true,
            'cover_image' => UploadedFile::fake()->create('new-cover.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Blog updated successfully');

        $blog->refresh();

        $this->assertSame('New title', $blog->title);
        $this->assertSame('New subtitle', $blog->subtitle);
        $this->assertSame('5 min', $blog->reading_time);
        $this->assertTrue((bool) $blog->is_published);
        $this->assertNotNull($blog->cover_image_path);

        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'title' => 'New title',
            'subtitle' => 'New subtitle',
            'reading_time' => '5 min',
            'is_published' => true,
        ]);

        $this->assertDatabaseCount('sections', 1);
        $this->assertFalse(Storage::disk('public')->exists('cover_images/old-cover.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($blog->cover_image_path));
    }

    public function test_blog_update_can_remove_cover_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user, [
            'cover_image_path' => 'cover_images/remove-me.jpg',
        ]);

        Storage::disk('public')->put('cover_images/remove-me.jpg', 'remove-me');

        Sanctum::actingAs($user);

        $response = $this->post("/api/blogs/{$blog->id}", [
            '_method' => 'PUT',
            'remove_cover_image' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Blog updated successfully');

        $blog->refresh();

        $this->assertNull($blog->cover_image_path);
        $this->assertFalse(Storage::disk('public')->exists('cover_images/remove-me.jpg'));
    }

    public function test_section_store_updates_with_image_and_returns_created_section(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user);

        Sanctum::actingAs($user);

        $response = $this->post("/api/blogs/{$blog->id}/sections", [
            'title' => 'Section title',
            'content' => 'Section content',
            'order' => 1,
            'image' => UploadedFile::fake()->create('section.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Blog section created successfully');

        $this->assertDatabaseHas('sections', [
            'blog_id' => $blog->id,
            'title' => 'Section title',
            'content' => 'Section content',
            'order' => 1,
        ]);

        $section = Section::where('blog_id', $blog->id)->first();
        $this->assertNotNull($section);
        $this->assertNotNull($section->image_path);
        $this->assertTrue(Storage::disk('public')->exists($section->image_path));
    }

    public function test_section_update_rejects_image_and_remove_flag_together(): void
    {
        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user);
        $section = $this->createSectionForBlog($blog, [
            'title' => 'Section title',
            'content' => 'Section content',
            'order' => 1,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/blogs/{$blog->id}/sections/{$section->id}", [
            '_method' => 'PUT',
            'title' => 'Updated section',
            'image' => UploadedFile::fake()->create('new-section.jpg', 100, 'image/jpeg'),
            'remove_image' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image', 'remove_image']);

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'title' => 'Section title',
        ]);
    }

    public function test_section_update_changes_content_order_and_replaces_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user);
        $section = $this->createSectionForBlog($blog, [
            'title' => 'Section title',
            'content' => 'Section content',
            'order' => 1,
            'image_path' => 'section_images/old-section.jpg',
        ]);

        Storage::disk('public')->put('section_images/old-section.jpg', 'old-section');

        Sanctum::actingAs($user);

        $response = $this->post("/api/blogs/{$blog->id}/sections/{$section->id}", [
            '_method' => 'PUT',
            'title' => 'Updated section',
            'content' => 'Updated content',
            'order' => 2,
            'image' => UploadedFile::fake()->create('new-section.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Blog section updated successfully');

        $section->refresh();

        $this->assertSame('Updated section', $section->title);
        $this->assertSame('Updated content', $section->content);
        $this->assertSame(2, (int) $section->order);
        $this->assertNotNull($section->image_path);

        $this->assertFalse(Storage::disk('public')->exists('section_images/old-section.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($section->image_path));
    }

    public function test_section_delete_reindexes_remaining_sections_from_zero(): void
    {
        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user);
        $firstSection = $this->createSectionForBlog($blog, [
            'title' => 'First',
            'content' => 'First content',
            'order' => 1,
        ]);
        $deletedSection = $this->createSectionForBlog($blog, [
            'title' => 'Second',
            'content' => 'Second content',
            'order' => 2,
        ]);
        $thirdSection = $this->createSectionForBlog($blog, [
            'title' => 'Third',
            'content' => 'Third content',
            'order' => 3,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/blogs/{$blog->id}/sections/{$deletedSection->id}");

        $response->assertOk();
        $response->assertJsonPath('message', 'Blog section deleted successfully');

        $remainingOrders = Section::where('blog_id', $blog->id)
            ->orderBy('order')
            ->pluck('order', 'id')
            ->all();

        $this->assertSame([
            $firstSection->id => 0,
            $thirdSection->id => 1,
        ], $remainingOrders);

        $this->assertDatabaseMissing('sections', [
            'id' => $deletedSection->id,
        ]);
    }

    public function test_section_reorder_endpoint_updates_orders(): void
    {
        $user = User::factory()->create();
        $blog = $this->createBlogForUser($user);
        $sectionOne = $this->createSectionForBlog($blog, [
            'title' => 'One',
            'content' => 'One content',
            'order' => 1,
        ]);
        $sectionTwo = $this->createSectionForBlog($blog, [
            'title' => 'Two',
            'content' => 'Two content',
            'order' => 2,
        ]);
        $sectionThree = $this->createSectionForBlog($blog, [
            'title' => 'Three',
            'content' => 'Three content',
            'order' => 3,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/blogs/{$blog->id}/sections/reorder", [
            'sections' => [
                ['id' => $sectionThree->id, 'order' => 1],
                ['id' => $sectionOne->id, 'order' => 2],
                ['id' => $sectionTwo->id, 'order' => 3],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Blog sections reordered successfully');

        $this->assertDatabaseHas('sections', [
            'id' => $sectionThree->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('sections', [
            'id' => $sectionOne->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas('sections', [
            'id' => $sectionTwo->id,
            'order' => 3,
        ]);
    }

    private function createBlogForUser(User $user, array $attributes = []): Blog
    {
        return Blog::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Blog title',
            'subtitle' => 'Blog subtitle',
            'reading_time' => '4 min',
            'cover_image_path' => null,
            'is_published' => false,
        ], $attributes));
    }

    private function createSectionForBlog(Blog $blog, array $attributes = []): Section
    {
        return Section::create(array_merge([
            'blog_id' => $blog->id,
            'title' => 'Section title',
            'content' => 'Section content',
            'order' => 1,
            'image_path' => null,
        ], $attributes));
    }
}