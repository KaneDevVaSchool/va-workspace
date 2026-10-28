<?php

namespace Tests\Unit\Identity;

use Mockery;
use Modules\Identity\App\Repositories\Contracts\PushSubscriptionRepositoryInterface;
use Modules\Identity\App\Services\WebPushService;
use PHPUnit\Framework\TestCase;

class WebPushServiceOptionsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): WebPushService
    {
        return new WebPushService(Mockery::mock(PushSubscriptionRepositoryInterface::class));
    }

    public function test_chat_push_is_high_urgency_with_short_ttl(): void
    {
        $options = $this->service()->deliveryOptions(['tag' => 'va-chat-42']);

        $this->assertSame(3600, $options['TTL']);
        $this->assertSame('high', $options['urgency']);
    }

    public function test_other_push_keeps_one_day_ttl_instead_of_library_default(): void
    {
        $options = $this->service()->deliveryOptions(['tag' => 'hrm-timesheet-abc']);

        $this->assertSame(86400, $options['TTL']);
        $this->assertSame('normal', $options['urgency']);
    }

    public function test_push_without_tag_uses_default_options(): void
    {
        $options = $this->service()->deliveryOptions([]);

        $this->assertSame(86400, $options['TTL']);
        $this->assertSame('normal', $options['urgency']);
    }
}
