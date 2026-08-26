<?php

namespace Modules\WorkspaceConfig\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\WorkspaceConfig\App\Http\Requests\ReorderSidebarLayoutRequest;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateSidebarSectionRequest;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateSidebarVisibilityRequest;
use Modules\WorkspaceConfig\App\Services\DepartmentSidebarConfigService;

/**
 * manager/workspace-config/sidebar — bật/tắt, đổi tên, kéo thả thứ tự và
 * đổi tên nhóm menu sidebar của CHÍNH phòng ban user đang đăng nhập.
 * department_id luôn lấy từ $request->user()->department_id.
 */
class WorkspaceConfigSidebarController extends Controller
{
    public function __construct(
        private readonly DepartmentSidebarConfigService $service,
        private readonly ActivityLogService $activityLogs,
    ) {}

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId ? (int) $departmentId : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    public function index(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return response()->json($this->payload($departmentId));
    }

    public function update(UpdateSidebarVisibilityRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        $data = $request->validated();
        $updateLabel = array_key_exists('custom_label', $data);
        $isVisible = array_key_exists('is_visible', $data) ? (bool) $data['is_visible'] : null;

        try {
            $config = $this->service->updateMenu(
                $departmentId,
                $data['menu_key'],
                $isVisible,
                $updateLabel,
                $updateLabel ? $data['custom_label'] : null,
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'workspace_config.sidebar.update',
            $this->activityMessage($data, $config, $updateLabel),
            $request->user(),
            'department_sidebar_config',
            $config->id,
        );

        $defaultLabel = $this->service->menuLabel($config->menu_key);
        $label = $this->service->effectiveLabel($config, $config->menu_key);

        return response()->json([
            'menu' => [
                'menu_key' => $config->menu_key,
                'is_visible' => $config->is_visible,
                'default_label' => $defaultLabel,
                'custom_label' => $config->custom_label,
                'label' => $label,
                'section' => $this->service->normalizeSection($config->section_key) ?? $this->service->defaultSection($config->menu_key),
                'default_section' => $this->service->defaultSection($config->menu_key),
                'sort_order' => $config->sort_order,
            ],
        ]);
    }

    public function reorder(ReorderSidebarLayoutRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        try {
            $this->service->reorderMenus(
                $departmentId,
                $request->validated('items'),
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'workspace_config.sidebar.reorder',
            'Sắp xếp lại thứ tự menu trái cho phòng ban',
            $request->user(),
            'department_sidebar_config',
            $departmentId,
        );

        return response()->json($this->payload($departmentId));
    }

    public function updateSection(UpdateSidebarSectionRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        $data = $request->validated();

        try {
            $sections = $this->service->updateSection(
                $departmentId,
                $data['section_key'],
                $data['custom_label'] ?? null,
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $updated = collect($sections)->firstWhere('id', $data['section_key']);
        $this->activityLogs->record(
            'workspace_config.sidebar.section_update',
            $updated && $updated['custom_label']
                ? 'Đổi tên nhóm "'.$this->service->sectionLabel($data['section_key']).'" thành "'.$updated['custom_label'].'" cho phòng ban'
                : 'Khôi phục tên nhóm "'.$this->service->sectionLabel($data['section_key']).'" cho phòng ban',
            $request->user(),
            'department_sidebar_config',
            $departmentId,
        );

        return response()->json([
            'sections' => $sections,
            'section' => $updated,
        ]);
    }

    /**
     * @return array{menus: \Illuminate\Support\Collection, sections: array}
     */
    private function payload(int $departmentId): array
    {
        return [
            'menus' => $this->service->forDepartment($departmentId),
            'sections' => $this->service->sectionsForDepartment($departmentId),
        ];
    }

    /**
     * @param  array{menu_key: string, is_visible?: bool, custom_label?: string|null}  $data
     */
    private function activityMessage(array $data, DepartmentSidebarConfig $config, bool $updateLabel): string
    {
        $defaultLabel = $this->service->menuLabel($config->menu_key);
        $effective = $this->service->effectiveLabel($config, $config->menu_key);
        $parts = [];

        if (array_key_exists('is_visible', $data)) {
            $parts[] = ($data['is_visible'] ? 'Bật' : 'Tắt').' menu "'.$effective.'"';
        }

        if ($updateLabel) {
            $parts[] = $config->custom_label
                ? 'đổi tên "'.$defaultLabel.'" thành "'.$config->custom_label.'"'
                : 'khôi phục tên mặc định "'.$defaultLabel.'"';
        }

        return implode('; ', $parts).' cho phòng ban';
    }
}
