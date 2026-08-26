<?php

namespace Tests\Unit\Social;

use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;
use Modules\Social\App\Services\SocialHashtagService;
use Tests\TestCase;

class SocialHashtagExtractTest extends TestCase
{
    private function service(): SocialHashtagService
    {
        return new SocialHashtagService($this->createMock(SocialPostRepositoryInterface::class));
    }

    public function test_extracts_unique_normalized_tags_including_vietnamese(): void
    {
        $tags = $this->service()->extractFromHtml(
            '<p>Chào năm học mới #TuyểnSinh #VAS2026 và #tuyểnsinh lần nữa</p>'
        );

        $this->assertSame([
            'tuyểnsinh' => 'TuyểnSinh',
            'vas2026' => 'VAS2026',
        ], $tags);
    }

    public function test_skips_url_fragments(): void
    {
        $tags = $this->service()->extractFromHtml(
            '<p>Xem https://vaschools.edu.vn/#gioithieu và #sukien</p>'
        );

        $this->assertSame(['sukien' => 'sukien'], $tags);
    }

    public function test_normalize_rejects_empty_and_symbols(): void
    {
        $this->assertNull(SocialHashtagService::normalize(''));
        $this->assertNull(SocialHashtagService::normalize('a-b'));
        $this->assertSame('hop_noi_bo', SocialHashtagService::normalize('Hop_Noi_Bo'));
    }
}
