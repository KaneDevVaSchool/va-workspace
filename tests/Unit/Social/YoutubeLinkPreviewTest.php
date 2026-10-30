<?php

namespace Tests\Unit\Social;

use Illuminate\Support\Facades\Http;
use Modules\Social\App\Models\SocialLinkPreview;
use Modules\Social\App\Services\SocialLinkPreviewService;
use Tests\TestCase;

class YoutubeLinkPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.youtube.key' => null]);
    }

    /**
     * @dataProvider youtubeUrlProvider
     */
    public function test_common_youtube_urls_resolve_to_the_same_video(string $url): void
    {
        Http::fake([
            'www.youtube.com/oembed*' => Http::response([
                'title' => 'Bật nó lên',
                'author_name' => 'SOOBIN',
                'thumbnail_url' => 'https://i.ytimg.com/vi/SeWt7lpZOCA/hqdefault.jpg',
            ]),
        ]);

        $preview = app(SocialLinkPreviewService::class)->previewForUrl($url);

        $this->assertNotNull($preview);
        $this->assertSame(SocialLinkPreview::PROVIDER_YOUTUBE, $preview['provider']);
        $this->assertSame('SeWt7lpZOCA', $preview['external_id']);
        $this->assertSame('Bật nó lên', $preview['title']);
        $this->assertSame('SOOBIN', $preview['site_name']);
        $this->assertSame('https://i.ytimg.com/vi/SeWt7lpZOCA/hqdefault.jpg', $preview['thumbnail_url']);
        $this->assertStringStartsWith('https://www.youtube.com/embed/SeWt7lpZOCA?', $preview['embed_url']);
        $this->assertStringContainsString('playsinline=1', $preview['embed_url']);
        $this->assertStringContainsString('rel=0', $preview['embed_url']);
        $this->assertStringContainsString('hl=vi', $preview['embed_url']);
        $this->assertStringNotContainsString('origin=', $preview['embed_url']);
        $this->assertStringNotContainsString('start=', $preview['embed_url']);
        $this->assertFalse($preview['is_live']);
    }

    /** @return array<string, array{string}> */
    public static function youtubeUrlProvider(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=SeWt7lpZOCA'],
            'youtu.be' => ['https://youtu.be/SeWt7lpZOCA'],
            'shorts' => ['https://www.youtube.com/shorts/SeWt7lpZOCA'],
            'live' => ['https://www.youtube.com/live/SeWt7lpZOCA'],
            'embed' => ['https://www.youtube.com/embed/SeWt7lpZOCA'],
            'cũ /v/' => ['https://www.youtube.com/v/SeWt7lpZOCA'],
            'mobile' => ['https://m.youtube.com/watch?v=SeWt7lpZOCA'],
            'music' => ['https://music.youtube.com/watch?v=SeWt7lpZOCA'],
            'nocookie' => ['https://www.youtube-nocookie.com/embed/SeWt7lpZOCA'],
        ];
    }

    public function test_timestamp_is_kept_as_start_seconds(): void
    {
        Http::fake([
            'www.youtube.com/oembed*' => Http::response([
                'title' => 'Video',
                'author_name' => 'Kênh',
            ]),
        ]);

        $service = app(SocialLinkPreviewService::class);

        $fromQuery = $service->previewForUrl('https://youtu.be/SeWt7lpZOCA?t=1m30s');
        $fromHash = $service->previewForUrl('https://www.youtube.com/watch?v=SeWt7lpZOCA#t=45');
        $fromClock = $service->previewForUrl('https://www.youtube.com/watch?v=SeWt7lpZOCA&t=1h2m3s');

        $this->assertStringContainsString('start=90', $fromQuery['embed_url']);
        $this->assertStringContainsString('start=45', $fromHash['embed_url']);
        $this->assertStringContainsString('start=3723', $fromClock['embed_url']);
    }

    public function test_data_api_prefers_maxres_and_marks_live(): void
    {
        config(['services.youtube.key' => 'test-key']);
        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'snippet' => [
                        'title' => 'Đang phát',
                        'description' => str_repeat('mô tả ', 400),
                        'channelTitle' => 'VA Schools',
                        'liveBroadcastContent' => 'live',
                        'thumbnails' => [
                            'high' => ['url' => 'https://i.ytimg.com/vi/SeWt7lpZOCA/hqdefault.jpg'],
                            'maxres' => ['url' => 'https://i.ytimg.com/vi/SeWt7lpZOCA/maxresdefault.jpg'],
                        ],
                    ],
                ]],
            ]),
        ]);

        $preview = app(SocialLinkPreviewService::class)->previewForUrl('https://www.youtube.com/watch?v=SeWt7lpZOCA');

        $this->assertSame('https://i.ytimg.com/vi/SeWt7lpZOCA/maxresdefault.jpg', $preview['thumbnail_url']);
        $this->assertSame('VA Schools', $preview['site_name']);
        $this->assertTrue($preview['is_live']);
        $this->assertLessThanOrEqual(1000, mb_strlen((string) $preview['description']));
        $this->assertStringEndsWith('…', (string) $preview['description']);
    }

    public function test_data_api_failure_falls_back_to_oembed(): void
    {
        config(['services.youtube.key' => 'test-key']);
        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['error' => 'denied'], 403),
            'www.youtube.com/oembed*' => Http::response([
                'title' => 'Từ oEmbed',
                'author_name' => 'Kênh',
            ]),
        ]);

        $preview = app(SocialLinkPreviewService::class)->previewForUrl('https://www.youtube.com/watch?v=SeWt7lpZOCA');

        $this->assertSame('Từ oEmbed', $preview['title']);
        $this->assertFalse($preview['is_live']);
    }

    public function test_url_without_a_video_id_does_not_call_youtube(): void
    {
        Http::fake();

        $preview = app(SocialLinkPreviewService::class)->previewForUrl('https://www.youtube.com/feed/trending');

        $this->assertNull($preview);
        Http::assertNothingSent();
    }

    public function test_oembed_failure_returns_null(): void
    {
        Http::fake([
            'www.youtube.com/oembed*' => Http::response('nope', 404),
        ]);

        $this->assertNull(
            app(SocialLinkPreviewService::class)->previewForUrl('https://www.youtube.com/watch?v=SeWt7lpZOCA')
        );
    }
}
