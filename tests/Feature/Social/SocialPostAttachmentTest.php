<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('public');
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
        Storage::fake('public');
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
}
