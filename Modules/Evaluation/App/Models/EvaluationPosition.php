<?php

namespace Modules\Evaluation\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Vị trí đánh giá" — danh mục chức danh HOẶC phòng ban dùng chung toàn hệ
 * thống.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $kind               'position' (chức danh) | 'department' (phòng ban)
 * @property string|null $description
 * @property string|null $hrm_position_uuid  tham chiếu đối chiếu HRM tương lai — không phải nguồn sự thật
 * @property int|null    $created_by
 */
class EvaluationPosition extends Model
{
    protected $table = 'evaluation_positions';

    public const KIND_POSITION = 'position';

    public const KIND_DEPARTMENT = 'department';

    protected $fillable = [
        'name',
        'kind',
        'description',
        'hrm_position_uuid',
        'created_by',
    ];
}
