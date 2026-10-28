<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\PushSubscription;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    /** @return array<string, mixed> */
    private function payload(string $endpoint = self::FCM_ENDPOINT): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'AuthToken'],
            'contentEncoding' => 'aes128gcm',
        ];
    }

    public function test_user_can_subscribe_and_unsubscribe(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->postJson('/api/notifications/push/subscribe', $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->id)->count());

        $this->actingAs($user)->deleteJson('/api/notifications/push/subscribe', ['endpoint' => self::FCM_ENDPOINT])
            ->assertOk();

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_subscribing_twice_with_same_endpoint_keeps_one_row(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->postJson('/api/notifications/push/subscribe', $this->payload())->assertOk();
        $this->actingAs($user)->postJson('/api/notifications/push/subscribe', $this->payload())->assertOk();

        $this->assertSame(1, PushSubscription::query()->count());
    }

    /**
     * @dataProvider disallowedEndpoints
     */
    public function test_endpoint_outside_push_services_is_rejected(string $endpoint): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->postJson('/api/notifications/push/subscribe', $this->payload($endpoint))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, PushSubscription::query()->count());
    }

    /** @return array<string, array{string}> */
    public static function disallowedEndpoints(): array
    {
        return [
            'http thường' => ['http://fcm.googleapis.com/fcm/send/abc'],
            'host nội bộ' => ['https://127.0.0.1/internal'],
            'host lạ' => ['https://evil.example.com/push'],
            'giả mạo bằng userinfo' => ['https://fcm.googleapis.com@evil.example.com/push'],
            'đuôi giống nhưng không phải subdomain' => ['https://notfcm.googleapis.com.evil.test/push'],
            'cổng lạ' => ['https://fcm.googleapis.com:8443/fcm/send/abc'],
        ];
    }

    /**
     * @dataProvider allowedEndpoints
     */
    public function test_known_push_services_are_accepted(string $endpoint): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->postJson('/api/notifications/push/subscribe', $this->payload($endpoint))
            ->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function allowedEndpoints(): array
    {
        return [
            'Chrome/Android (FCM)' => ['https://fcm.googleapis.com/fcm/send/abc'],
            'Firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'],
            'Safari/iOS' => ['https://web.push.apple.com/QAbc'],
            'Edge (WNS)' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'],
        ];
    }

    public function test_same_browser_moves_to_the_latest_user(): void
    {
        $first = User::factory()->create(['status' => 'active']);
        $second = User::factory()->create(['status' => 'active']);

        $this->actingAs($first)->postJson('/api/notifications/push/subscribe', $this->payload())->assertOk();
        $this->actingAs($second)->postJson('/api/notifications/push/subscribe', $this->payload())->assertOk();

        $this->assertSame(0, PushSubscription::query()->where('user_id', $first->id)->count());
        $this->assertSame(1, PushSubscription::query()->where('user_id', $second->id)->count());
    }

    public function test_user_keeps_only_the_most_recent_subscriptions(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        for ($i = 1; $i <= 12; $i++) {
            $this->actingAs($user)
                ->postJson('/api/notifications/push/subscribe', $this->payload("https://fcm.googleapis.com/fcm/send/device-{$i}"))
                ->assertOk();
        }

        $endpoints = PushSubscription::query()->where('user_id', $user->id)->pluck('endpoint');

        $this->assertCount(10, $endpoints);
        $this->assertFalse($endpoints->contains('https://fcm.googleapis.com/fcm/send/device-1'));
        $this->assertTrue($endpoints->contains('https://fcm.googleapis.com/fcm/send/device-12'));
    }

    public function test_unsubscribe_requires_endpoint_and_only_touches_own_rows(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);

        $this->actingAs($owner)->postJson('/api/notifications/push/subscribe', $this->payload())->assertOk();

        $this->actingAs($other)->deleteJson('/api/notifications/push/subscribe', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->actingAs($other)->deleteJson('/api/notifications/push/subscribe', ['endpoint' => self::FCM_ENDPOINT])
            ->assertOk();

        $this->assertSame(1, PushSubscription::query()->where('user_id', $owner->id)->count());
    }
}
