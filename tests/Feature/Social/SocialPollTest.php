<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Social\App\Models\SocialPoll;
use Tests\TestCase;

class SocialPollTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function createPollPost(User $user, array $poll = [], array $extra = []): array
    {
        $payload = array_merge([
            'content' => 'Nên tổ chức team building ở đâu?',
            'poll' => array_merge([
                'options' => ['Đà Lạt', 'Nha Trang', 'Vũng Tàu'],
                'allow_multiple' => false,
            ], $poll),
        ], $extra);

        return $this->actingAs($user)
            ->postJson('/api/social/posts', $payload)
            ->assertCreated()
            ->json();
    }

    public function test_can_create_post_with_poll(): void
    {
        $user = $this->makeUser();
        $data = $this->createPollPost($user);

        $this->assertNotNull($data['post']['poll']);
        $this->assertFalse($data['post']['poll']['allow_multiple']);
        $this->assertTrue($data['post']['poll']['can_vote']);
        $this->assertTrue($data['post']['poll']['can_close']);
        $this->assertFalse($data['post']['poll']['show_results']);
        $this->assertCount(3, $data['post']['poll']['options']);
        $this->assertNull($data['post']['poll']['options'][0]['votes_count']);
        $this->assertSame('Đà Lạt', $data['post']['poll']['options'][0]['label']);
    }

    public function test_can_create_poll_only_post_without_content(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'poll' => [
                    'options' => ['Có', 'Không'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('post.poll.options.0.label', 'Có')
            ->assertJsonPath('post.poll.options.1.label', 'Không');
    }

    public function test_empty_post_without_poll_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_poll_requires_at_least_two_options(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => 'Thiếu phương án',
                'poll' => ['options' => ['Một mình']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['poll.options']);
    }

    public function test_single_choice_vote_and_change_and_unvote(): void
    {
        $author = $this->makeUser();
        $voter = $this->makeUser();
        $post = $this->createPollPost($author)['post'];
        $first = $post['poll']['options'][0]['id'];
        $second = $post['poll']['options'][1]['id'];

        $voted = $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', ['option_id' => $first])
            ->assertOk()
            ->json('poll');

        $this->assertTrue($voted['show_results']);
        $this->assertSame(1, $voted['total_votes']);
        $this->assertSame([$first], $voted['my_option_ids']);
        $this->assertSame(1, $voted['options'][0]['votes_count']);
        $this->assertSame(100, $voted['options'][0]['percent']);

        $changed = $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', ['option_id' => $second])
            ->assertOk()
            ->json('poll');

        $this->assertSame([$second], $changed['my_option_ids']);
        $this->assertSame(0, $changed['options'][0]['votes_count']);
        $this->assertSame(1, $changed['options'][1]['votes_count']);

        $unvoted = $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', ['option_id' => $second])
            ->assertOk()
            ->json('poll');

        $this->assertSame([], $unvoted['my_option_ids']);
        $this->assertFalse($unvoted['show_results']);
        $this->assertNull($unvoted['options'][0]['votes_count']);
    }

    public function test_multiple_choice_toggles_each_option(): void
    {
        $author = $this->makeUser();
        $voter = $this->makeUser();
        $post = $this->createPollPost($author, ['allow_multiple' => true])['post'];
        $first = $post['poll']['options'][0]['id'];
        $second = $post['poll']['options'][1]['id'];

        $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', ['option_id' => $first])
            ->assertOk();

        $both = $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', ['option_id' => $second])
            ->assertOk()
            ->json('poll');

        $this->assertEqualsCanonicalizing([$first, $second], $both['my_option_ids']);
        $this->assertSame(2, $both['total_votes']);
        $this->assertTrue($both['allow_multiple']);

        $one = $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', ['option_id' => $first])
            ->assertOk()
            ->json('poll');

        $this->assertSame([$second], $one['my_option_ids']);
        $this->assertSame(1, $one['total_votes']);
    }

    public function test_results_hidden_from_other_users_until_they_vote(): void
    {
        $author = $this->makeUser();
        $voter = $this->makeUser();
        $viewer = $this->makeUser();
        $post = $this->createPollPost($author)['post'];

        $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', [
                'option_id' => $post['poll']['options'][0]['id'],
            ])
            ->assertOk();

        $feed = $this->actingAs($viewer)
            ->getJson('/api/social/posts')
            ->assertOk()
            ->json('posts.0.poll');

        $this->assertFalse($feed['show_results']);
        $this->assertNull($feed['total_votes']);
        $this->assertNull($feed['options'][0]['votes_count']);
        $this->assertSame([], $feed['my_option_ids']);
    }

    public function test_author_can_close_poll_and_voting_stops(): void
    {
        $author = $this->makeUser();
        $voter = $this->makeUser();
        $post = $this->createPollPost($author)['post'];

        $closed = $this->actingAs($author)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/close')
            ->assertOk()
            ->json('poll');

        $this->assertTrue($closed['is_closed']);
        $this->assertTrue($closed['is_ended']);
        $this->assertTrue($closed['show_results']);
        $this->assertFalse($closed['can_vote']);
        $this->assertFalse($closed['can_close']);

        $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', [
                'option_id' => $post['poll']['options'][0]['id'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['option_id']);
    }

    public function test_non_author_cannot_close_poll(): void
    {
        $author = $this->makeUser();
        $other = $this->makeUser();
        $post = $this->createPollPost($author)['post'];

        $this->actingAs($other)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/close')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['poll']);
    }

    public function test_cannot_vote_after_deadline(): void
    {
        $author = $this->makeUser();
        $voter = $this->makeUser();
        $post = $this->createPollPost($author, [
            'ends_at' => now()->addHour()->toIso8601String(),
        ])['post'];

        SocialPoll::query()->where('post_id', $post['id'])->update([
            'ends_at' => now()->subMinute(),
        ]);

        $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', [
                'option_id' => $post['poll']['options'][0]['id'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['option_id']);
    }

    public function test_voters_list_requires_having_voted(): void
    {
        $author = $this->makeUser();
        $voter = $this->makeUser();
        $viewer = $this->makeUser();
        $post = $this->createPollPost($author)['post'];
        $optionId = $post['poll']['options'][0]['id'];

        $this->actingAs($viewer)
            ->getJson('/api/social/posts/'.$post['id'].'/poll/votes?option_id='.$optionId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['poll']);

        $this->actingAs($voter)
            ->postJson('/api/social/posts/'.$post['id'].'/poll/votes', ['option_id' => $optionId])
            ->assertOk();

        $this->actingAs($voter)
            ->getJson('/api/social/posts/'.$post['id'].'/poll/votes?option_id='.$optionId)
            ->assertOk()
            ->assertJsonPath('users.0.user.id', $voter->id)
            ->assertJsonPath('users.0.option_id', $optionId);
    }

    public function test_past_deadline_is_rejected_when_creating(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => 'Hết hạn rồi',
                'poll' => [
                    'options' => ['A', 'B'],
                    'ends_at' => now()->subHour()->toIso8601String(),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['poll.ends_at']);
    }

    public function test_poll_stores_title_and_content(): void
    {
        $user = $this->makeUser();
        $data = $this->createPollPost($user, [
            'title' => 'Team building 2026',
            'content' => 'Chọn địa điểm phù hợp cả phòng.',
        ]);

        $this->assertSame('Team building 2026', $data['post']['poll']['title']);
        $this->assertSame('Chọn địa điểm phù hợp cả phòng.', $data['post']['poll']['content']);
        $this->assertNull($data['post']['poll']['image_url']);
    }

    public function test_poll_stores_cover_image(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post('/api/social/posts', [
                'poll' => [
                    'title' => 'Có ảnh',
                    'options' => ['A', 'B'],
                    'image' => UploadedFile::fake()->image('cover.jpg', 40, 30),
                ],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('post.poll.title', 'Có ảnh')
            ->assertJsonPath('post.poll.image_url', fn ($url) => is_string($url) && $url !== '');
    }
}
