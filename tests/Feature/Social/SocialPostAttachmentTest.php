<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Social\App\Models\SocialLinkPreview;
use Tests\TestCase;

class SocialPostAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_post_accepts_up_to_ten_images(): void
    {
        Storage::fake('s3');
        $user = $this->makeUser();
        $files = [];
        for ($i = 1; $i <= 10; $i++) {
            $files[] = UploadedFile::fake()->image("photo{$i}.jpg", 20, 20);
        }

        $this->actingAs($user)
            ->post('/api/social/posts', [
                'content' => 'Mười ảnh',
                'attachments' => $files,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('post.attachments', fn ($attachments) => count($attachments) === 10);
    }

    public function test_post_rejects_more_than_ten_attachments(): void
    {
        Storage::fake('s3');
        $user = $this->makeUser();
        $files = [];
        for ($i = 1; $i <= 11; $i++) {
            $files[] = UploadedFile::fake()->image("photo{$i}.jpg", 20, 20);
        }

        $this->actingAs($user)
            ->post('/api/social/posts', [
                'content' => 'Quá nhiều ảnh',
                'attachments' => $files,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachments']);
    }

    public function test_shared_post_includes_original_attachments(): void
    {
        Storage::fake('s3');
        $author = $this->makeUser();
        $sharer = $this->makeUser();

        $postId = $this->actingAs($author)
            ->post('/api/social/posts', [
                'content' => 'Bài có ảnh',
                'attachments' => [UploadedFile::fake()->image('cover.jpg', 40, 40)],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($sharer)
            ->postJson("/api/social/posts/{$postId}/share", ['caption' => 'Chia sẻ kèm ảnh'])
            ->assertCreated()
            ->assertJsonPath('post.shared_from.attachments.0.type', 'image')
            ->assertJsonPath('post.shared_from.attachments.0.name', 'cover.jpg');
    }

    public function test_dismissed_link_preview_is_not_recreated(): void
    {
        Http::fake([
            'www.youtube.com/oembed*' => Http::response([
                'title' => 'Video',
                'thumbnail_url' => 'https://img.youtube.com/vi/abc/hqdefault.jpg',
            ]),
        ]);

        $user = $this->makeUser();
        $url = 'https://www.youtube.com/watch?v=abcdefghijk';

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => "<p>Xem {$url}</p>",
                'dismissed_link_previews' => [$url],
            ])
            ->assertCreated();

        $this->assertSame(0, SocialLinkPreview::query()->count());
    }
}
