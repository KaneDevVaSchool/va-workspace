<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;

class UpdateEvaluationScoreKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'classification_criterion_id',
            'difficulty_criterion_id',
            'progress_criterion_id',
            'quality_criterion_id',
        ] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
        if ($this->has('mode') && $this->input('mode') === '') {
            $this->merge(['mode' => null]);
        }
    }

    public function rules(): array
    {
        $departmentId = (int) $this->user()?->department_id;

        return [
            'mode' => ['nullable', 'string', Rule::in(EvaluationScoreKit::MODES)],
            'base_score' => ['sometimes', 'numeric', 'min:0', 'max:9999'],
            'task_base_score' => ['sometimes', 'numeric', 'min:0', 'max:9999'],
            'quality_bonus_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'points_per_completed_task' => ['sometimes', 'numeric', 'min:-999', 'max:999'],
            'points_per_incomplete_task' => ['sometimes', 'numeric', 'min:-999', 'max:999'],
            'use_project_importance' => ['sometimes', 'boolean'],
            'classification_criterion_id' => $this->scaleCriterionRules($departmentId, 'Thang xếp loại', 2),
            'difficulty_criterion_id' => $this->scaleCriterionRules($departmentId, 'Thang độ khó'),
            'progress_criterion_id' => $this->scaleCriterionRules($departmentId, 'Thang tiến độ'),
            'quality_criterion_id' => $this->scaleCriterionRules($departmentId, 'Thang chất lượng'),
            'classification_use_default' => ['sometimes', 'boolean'],
            'difficulty_use_default' => ['sometimes', 'boolean'],
            'progress_use_default' => ['sometimes', 'boolean'],
            'quality_use_default' => ['sometimes', 'boolean'],
            'base_adjust_levels' => [
                'sometimes',
                'array',
                'min:'.EvaluationScoreKit::CLASSIFICATION_LEVEL_MIN,
                'max:'.EvaluationScoreKit::CLASSIFICATION_LEVEL_MAX,
            ],
            'base_adjust_levels.*.code' => ['nullable', 'string', 'max:8'],
            'base_adjust_levels.*.label' => ['required_with:base_adjust_levels', 'string', 'max:80'],
            'base_adjust_levels.*.score' => ['required_with:base_adjust_levels', 'numeric', 'min:0', 'max:9999'],
            'base_adjust_levels.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:99'],
            'weighted_task_levels' => ['sometimes', 'array', 'min:1', 'max:'.EvaluationScoreKit::SCALE_LEVEL_MAX],
            'weighted_task_levels.*.code' => ['nullable', 'string', 'max:8'],
            'weighted_task_levels.*.label' => ['required_with:weighted_task_levels', 'string', 'max:80'],
            'weighted_task_levels.*.score' => ['required_with:weighted_task_levels', 'numeric', 'min:0.01', 'max:9999'],
            'progress_levels' => ['sometimes', 'array', 'min:1', 'max:'.EvaluationScoreKit::SCALE_LEVEL_MAX],
            'progress_levels.*.code' => ['nullable', 'string', 'max:8'],
            'progress_levels.*.label' => ['required_with:progress_levels', 'string', 'max:80'],
            'progress_levels.*.score' => ['required_with:progress_levels', 'numeric', 'min:0.01', 'max:9999'],
            'quality_levels' => ['sometimes', 'array', 'min:1', 'max:'.EvaluationScoreKit::SCALE_LEVEL_MAX],
            'quality_levels.*.code' => ['nullable', 'string', 'max:8'],
            'quality_levels.*.label' => ['required_with:quality_levels', 'string', 'max:80'],
            'quality_levels.*.score' => ['required_with:quality_levels', 'numeric', 'min:0.01', 'max:9999'],
            'performance_levels' => ['sometimes', 'array', 'min:2', 'max:'.EvaluationScoreKit::CLASSIFICATION_LEVEL_MAX],
            'performance_levels.*.code' => ['nullable', 'string', 'max:8'],
            'performance_levels.*.label' => ['required_with:performance_levels', 'string', 'max:80'],
            'performance_levels.*.score' => ['required_with:performance_levels', 'numeric', 'min:0', 'max:9999'],
            'formula' => ['sometimes', 'array'],
            'formula.base' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'formula.done' => ['sometimes', 'string', Rule::in(['add', 'sub', 'off'])],
            'formula.undone' => ['sometimes', 'string', Rule::in(['add', 'sub', 'off'])],
            'formula.weight' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'formula.project' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'formula.progress' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'formula.quality' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'formula.contrib' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'formula.lock_difficulty' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'change_context' => ['sometimes', 'string', Rule::in(['manual', 'mode_change', 'reset'])],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.in' => 'Cách tính điểm không hợp lệ.',
            'base_score.numeric' => 'Điểm khởi đầu phải là số.',
            'base_score.min' => 'Điểm khởi đầu không được âm.',
            'base_score.max' => 'Điểm khởi đầu quá lớn.',
            'task_base_score.numeric' => 'Điểm cơ bản mỗi việc phải là số.',
            'task_base_score.min' => 'Điểm cơ bản mỗi việc không được âm.',
            'task_base_score.max' => 'Điểm cơ bản mỗi việc quá lớn.',
            'quality_bonus_percent.numeric' => 'Bonus chất lượng phải là số.',
            'quality_bonus_percent.min' => 'Bonus chất lượng không được âm.',
            'quality_bonus_percent.max' => 'Bonus chất lượng không được quá 100%.',
            'points_per_completed_task.numeric' => 'Điểm mỗi việc hoàn thành phải là số.',
            'points_per_incomplete_task.numeric' => 'Điểm mỗi việc chưa hoàn thành phải là số.',
            'base_adjust_levels.min' => 'Thang xếp loại phải có ít nhất '.EvaluationScoreKit::CLASSIFICATION_LEVEL_MIN.' mức.',
            'base_adjust_levels.max' => 'Thang xếp loại không được quá '.EvaluationScoreKit::CLASSIFICATION_LEVEL_MAX.' mức.',
            'base_adjust_levels.*.label.required_with' => 'Tên mức xếp loại không được để trống.',
            'weighted_task_levels.min' => 'Thang độ khó phải có ít nhất 1 mức.',
            'weighted_task_levels.max' => 'Thang độ khó có quá nhiều mức.',
            'weighted_task_levels.*.label.required_with' => 'Tên mức độ khó không được để trống.',
            'weighted_task_levels.*.score.min' => 'Hệ số nhân phải lớn hơn 0.',
            'progress_levels.min' => 'Thang tiến độ phải có ít nhất 1 mức.',
            'progress_levels.max' => 'Thang tiến độ có quá nhiều mức.',
            'progress_levels.*.label.required_with' => 'Tên mức tiến độ không được để trống.',
            'progress_levels.*.score.min' => 'Hệ số tiến độ phải lớn hơn 0.',
            'quality_levels.min' => 'Thang chất lượng phải có ít nhất 1 mức.',
            'quality_levels.max' => 'Thang chất lượng có quá nhiều mức.',
            'quality_levels.*.label.required_with' => 'Tên mức chất lượng không được để trống.',
            'quality_levels.*.score.min' => 'Hệ số chất lượng phải lớn hơn 0.',
            'performance_levels.min' => 'Thang xếp loại phải có ít nhất 2 mức.',
            'performance_levels.max' => 'Thang xếp loại có quá nhiều mức.',
            'performance_levels.*.label.required_with' => 'Tên mức hiệu suất không được để trống.',
            'formula.base.in' => 'Hạng mục điểm khởi đầu không hợp lệ.',
            'formula.done.in' => 'Hạng mục việc hoàn thành không hợp lệ.',
            'formula.undone.in' => 'Hạng mục việc chưa hoàn thành không hợp lệ.',
            'formula.weight.in' => 'Hạng mục độ khó không hợp lệ.',
            'formula.project.in' => 'Hạng mục mức dự án không hợp lệ.',
            'formula.progress.in' => 'Hạng mục tiến độ không hợp lệ.',
            'formula.quality.in' => 'Hạng mục chất lượng không hợp lệ.',
            'formula.contrib.in' => 'Hạng mục trọng số đóng góp không hợp lệ.',
            'formula.lock_difficulty.in' => 'Hạng mục khóa độ khó không hợp lệ.',
            'change_context.in' => 'Ngữ cảnh thay đổi khung chấm điểm không hợp lệ.',
        ];
    }

    /** @return array<int, mixed> */
    private function scaleCriterionRules(
        int $departmentId,
        string $label,
        int $minimumLevels = 1,
    ): array {
        return [
            'nullable',
            'integer',
            function (string $attribute, mixed $value, \Closure $fail) use ($departmentId, $label, $minimumLevels) {
                if ($value === null || $value === '' || $departmentId < 1) {
                    return;
                }

                $criterion = EvaluationCriteria::query()
                    ->where('id', (int) $value)
                    ->where('department_id', $departmentId)
                    ->first();

                if ($criterion === null) {
                    $fail($label.' phải thuộc phòng ban này.');

                    return;
                }
                if ($criterion->type !== 'scale') {
                    $fail($label.' phải là tiêu chí thang điểm.');

                    return;
                }
                if (! $criterion->is_active) {
                    $fail($label.' phải là tiêu chí đang áp dụng.');

                    return;
                }
                if (count($criterion->levels ?? []) < $minimumLevels) {
                    $fail($label.' phải có ít nhất '.$minimumLevels.' mức.');
                }
            },
        ];
    }
}
