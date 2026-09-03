<?php

namespace Modules\Report\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần chốt cột điểm / cột tiêu chí của báo cáo.
 *
 * @property int $id
 * @property int $report_id
 * @property string $revision 1.0 | 1.1 | …
 * @property string $kind original | appendix
 * @property list<int> $criterion_ids
 * @property list<string> $column_keys
 * @property string|null $note
 * @property int|null $created_by
 */
class ReportDisplayRevision extends Model
{
    public const KIND_ORIGINAL = 'original';

    public const KIND_APPENDIX = 'appendix';

    public const FIRST = '1.0';

    protected $table = 'report_display_revisions';

    protected $fillable = [
        'report_id',
        'revision',
        'kind',
        'criterion_ids',
        'column_keys',
        'note',
        'created_by',
    ];

    protected $casts = [
        'criterion_ids' => 'array',
        'column_keys' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
