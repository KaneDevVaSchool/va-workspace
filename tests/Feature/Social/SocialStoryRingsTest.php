<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Social\App\Events\StoryRingsUpdated;
use Tests\TestCase;

class SocialStoryRingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_rings_include_chat_contacts_without_stories(): void
    {
        $viewer = $this->makeUser();
        $withStory = $this->makeUser();
        $contactOnly = $this->makeUser();

        $this->actingAs($viewer)
            ->postJson('/api/chat/conversations', ['user_id' => $withStory->id])
            ->assertCreated();
        $this->actingAs($viewer)
            ->postJson('/api/chat/conversations', ['user_id' => $contactOnly->id])
            ->assertCreated();

        Storage::fake('local');
        $this->actingAs($withStory)
            ->postJson('/api/social/stories', [
                'file' => UploadedFile::fake()->image('story.jpg'),
            ])
            ->assertCreated();

        $payload = $this->actingAs($viewer)
            ->getJson('/api/social/stories')
            ->assertOk()
            ->json();

        $rings = collect($payload['rings']);
        $storyRing = $rings->firstWhere('user.id', $withStory->id);
        $idleRing = $rings->firstWhere('user.id', $contactOnly->id);

        $this->assertNotNull($storyRing);
        $this->assertTrue($storyRing['has_stories']);
        $this->assertTrue($storyRing['unseen']);

        $this->assertNotNull($idleRing);
        $this->assertFalse($idleRing['has_stories']);
        $this->assertSame([], $idleRing['stories']);
    }

    public function test_viewing_story_marks_ring_as_seen(): void
    {
        $viewer = $this->makeUser();
        $author = $this->makeUser();

        Storage::fake('local');
        $storyId = $this->actingAs($author)
            ->postJson('/api/social/stories', [
                'file' => UploadedFile::fake()->image('story.jpg'),
            ])
            ->assertCreated()
            ->json('story.id');

        $before = $this->actingAs($viewer)
            ->getJson('/api/social/stories')
            ->assertOk()
            ->json();

        $ring = collect($before['rings'])->firstWhere('user.id', $author->id);
        $this->assertTrue($ring['unseen']);

        $this->actingAs($viewer)
            ->postJson("/api/social/stories/{$storyId}/view")
            ->assertOk();

        $after = $this->actingAs($viewer)
            ->getJson('/api/social/stories')
            ->assertOk()
            ->json();

        $ringAfter = collect($after['rings'])->firstWhere('user.id', $author->id);
        $this->assertFalse($ringAfter['unseen']);
        $this->assertTrue($ringAfter['stories'][0]['viewed']);
    }

    public function test_owner_can_list_story_viewers(): void
    {
        $author = $this->makeUser();
        $viewer = $this->makeUser();

        Storage::fake('local');
        $storyId = $this->actingAs($author)
            ->postJson('/api/social/stories', [
                'file' => UploadedFile::fake()->image('story.jpg'),
            ])
            ->assertCreated()
            ->json('story.id');

        $this->actingAs($viewer)
            ->postJson("/api/social/stories/{$storyId}/view")
            ->assertOk();

        $payload = $this->actingAs($author)
            ->getJson("/api/social/stories/{$storyId}/viewers")
            ->assertOk()
            ->json();

        $this->assertSame(1, $payload['view_count']);
        $this->assertCount(1, $payload['viewers']);
        $this->assertSame($viewer->id, $payload['viewers'][0]['user']['id']);

        $rings = $this->actingAs($author)
            ->getJson('/api/social/stories')
            ->assertOk()
            ->json('mine.stories.0');

        $this->assertSame(1, $rings['view_count']);
        $this->assertSame($viewer->id, $rings['viewer_preview'][0]['id']);

        $this->actingAs($viewer)
            ->getJson("/api/social/stories/{$storyId}/viewers")
            ->assertForbidden();
    }

    public function test_posting_story_broadcasts_rings_refresh_to_chat_contacts(): void
    {
        Event::fake([StoryRingsUpdated::class]);

        $author = $this->makeUser();
        $contact = $this->makeUser();

        $this->actingAs($author)
            ->postJson('/api/chat/conversations', ['user_id' => $contact->id])
            ->assertCreated();

        Storage::fake('local');
        $this->actingAs($author)
            ->postJson('/api/social/stories', [
                'file' => UploadedFile::fake()->image('story.jpg'),
            ])
            ->assertCreated();

        Event::assertDispatched(StoryRingsUpdated::class, fn (StoryRingsUpdated $event) => $event->userId === $contact->id);
        Event::assertDispatched(StoryRingsUpdated::class, fn (StoryRingsUpdated $event) => $event->userId === $author->id);
    }

    public function test_viewer_can_react_to_story(): void
    {
        $author = $this->makeUser();
        $viewer = $this->makeUser();

        Storage::fake('local');
        $storyId = $this->actingAs($author)
            ->postJson('/api/social/stories', [
                'file' => UploadedFile::fake()->image('story.jpg'),
            ])
            ->assertCreated()
            ->json('story.id');

        $this->actingAs($viewer)
            ->postJson("/api/social/stories/{$storyId}/reactions", ['type' => 'love'])
            ->assertOk()
            ->assertJson(['my_reaction' => 'love']);

        $ring = $this->actingAs($viewer)
            ->getJson('/api/social/stories')
            ->assertOk()
            ->json();

        $authorRing = collect($ring['rings'])->firstWhere('user.id', $author->id);
        $this->assertSame('love', $authorRing['stories'][0]['my_reaction']);
    }
}
