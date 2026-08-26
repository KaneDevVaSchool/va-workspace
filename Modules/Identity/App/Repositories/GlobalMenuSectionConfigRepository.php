<?php

namespace Modules\Identity\App\Repositories;

use Modules\Identity\App\Models\GlobalMenuSectionConfig;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuSectionConfigRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho GlobalMenuSectionConfig.
 */
class GlobalMenuSectionConfigRepository implements GlobalMenuSectionConfigRepositoryInterface
{
    public function sectionLabels(): array
    {
        return GlobalMenuSectionConfig::query()
            ->whereNotNull('custom_label')
            ->where('custom_label', '!=', '')
            ->pluck('custom_label', 'section_key')
            ->all();
    }

    public function setSectionLabel(string $sectionKey, ?string $label, ?int $updatedBy): GlobalMenuSectionConfig
    {
        return GlobalMenuSectionConfig::query()->updateOrCreate(
            ['section_key' => $sectionKey],
            ['custom_label' => ($label !== null && $label !== '') ? $label : null, 'updated_by' => $updatedBy],
        );
    }
}
