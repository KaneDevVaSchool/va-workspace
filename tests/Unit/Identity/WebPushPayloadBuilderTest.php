<?php

namespace Tests\Unit\Identity;

use App\Models\User;
use Modules\Identity\App\Services\NotificationService;
use Modules\Identity\App\Services\WebPushPayloadBuilder;
use Tests\TestCase;

class WebPushPayloadBuilderTest extends TestCase
{
    public function test_chat_push_uses_brand_and_actor_message(): void
    {
        $builder = new WebPushPayloadBuilder;
        $actor = new User(['name' => 'Mai Anh']);

        $payload = $builder->build(
            NotificationService::TYPE_CHAT_MESSAGE,
            $actor,
            'Mai Anh đã gửi cho bạn một tin nhắn',
            'Xin chào nhé',
        );

        $this->assertSame('VA Workspace', $payload['title']);
        $this->assertSame('Mai Anh: Xin chào nhé', $payload['body']);
    }

    public function test_task_assigned_push_highlights_task_title(): void
    {
        $builder = new WebPushPayloadBuilder;
        $actor = new User(['name' => 'Tuấn']);

        $payload = $builder->build(
            NotificationService::TYPE_TASK_ASSIGNED,
            $actor,
            'Bạn được giao công việc mới',
            'Hoàn thiện báo cáo tuần',
        );

        $this->assertSame('VA Workspace', $payload['title']);
        $this->assertSame('Tuấn · Giao việc · Hoàn thiện báo cáo tuần', $payload['body']);
    }

    public function test_push_body_override_wins(): void
    {
        $builder = new WebPushPayloadBuilder;

        $payload = $builder->build(
            NotificationService::TYPE_LIKE_POST,
            null,
            'ignored',
            null,
            ['push_body' => 'Dòng tùy chỉnh'],
        );

        $this->assertSame('Dòng tùy chỉnh', $payload['body']);
    }

    public function test_push_body_override_keeps_line_breaks(): void
    {
        $builder = new WebPushPayloadBuilder;

        $payload = $builder->build(
            NotificationService::TYPE_CHAT_MESSAGE,
            null,
            'ignored',
            null,
            ['push_body' => "Mai Anh · Nhóm Kinh doanh\n\n  🙂   Đã gửi một sticker  "],
        );

        $this->assertSame("Mai Anh · Nhóm Kinh doanh\n🙂 Đã gửi một sticker", $payload['body']);
    }

    public function test_chat_phone_notification_has_fixed_title_and_message_body(): void
    {
        $builder = new WebPushPayloadBuilder;
        $actor = new User(['name' => 'Mai Anh']);
        $formatted = $builder->build(
            NotificationService::TYPE_CHAT_MESSAGE,
            $actor,
            'Mai Anh đã gửi cho bạn một tin nhắn',
            'Xin chào nhé',
        );

        $phone = $builder->forPhone(NotificationService::TYPE_CHAT_MESSAGE, $formatted);

        $this->assertSame('1 lời nhắn từ VA', $phone['title']);
        $this->assertSame('Mai Anh: Xin chào nhé', $phone['body']);
    }
}
