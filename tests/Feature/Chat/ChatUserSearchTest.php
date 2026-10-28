<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUserSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Đường tìm theo bảng users (không có HRM_DB_*).
        config(['database.connections.hrm.database' => null]);
    }

    private function makeUser(string $name): User
    {
        return User::factory()->create(['name' => $name, 'status' => 'active']);
    }

    public function test_search_returns_a_page_and_lets_the_client_load_the_rest(): void
    {
        $me = $this->makeUser('Người tìm');
        foreach (range(1, 24) as $i) {
            $this->makeUser(sprintf('Đồng nghiệp %02d', $i));
        }

        $first = $this->actingAs($me)->getJson('/api/chat/users/search?q=')->assertOk();
        $this->assertCount(20, $first->json('users'));
        $this->assertTrue($first->json('has_more'));

        $second = $this->actingAs($me)->getJson('/api/chat/users/search?q=&offset=20')->assertOk();
        $this->assertCount(4, $second->json('users'));
        $this->assertFalse($second->json('has_more'));

        $ids = array_merge(array_column($first->json('users'), 'id'), array_column($second->json('users'), 'id'));
        $this->assertCount(24, array_unique($ids));
        $this->assertNotContains($me->id, $ids);
    }

    public function test_every_typed_word_must_match_but_not_in_order(): void
    {
        $me = $this->makeUser('Người tìm');
        $target = $this->makeUser('Nguyễn Văn Khoa');
        $this->makeUser('Nguyễn Thị Lan');

        $ids = array_column(
            $this->actingAs($me)->getJson('/api/chat/users/search?q='.rawurlencode('khoa nguyễn'))->assertOk()->json('users'),
            'id',
        );

        $this->assertSame([$target->id], $ids);
    }
}
