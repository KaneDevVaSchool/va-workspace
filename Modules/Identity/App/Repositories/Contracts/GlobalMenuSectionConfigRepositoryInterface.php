<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Modules\Identity\App\Models\GlobalMenuSectionConfig;

/**
 * Contract cho tầng Repository — tên tuỳ chỉnh section menu toàn hệ thống.
 */
interface GlobalMenuSectionConfigRepositoryInterface
{
    /**
     * Trả map section_key => custom_label cho mọi section đã đổi tên.
     *
     * @return array<string, string>
     */
    public function sectionLabels(): array;

    public function setSectionLabel(string $sectionKey, ?string $label, ?int $updatedBy): GlobalMenuSectionConfig;
}
