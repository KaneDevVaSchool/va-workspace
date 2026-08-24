<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lối tắt trang của một user (header → popover Lối tắt).
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string $path
 * @property bool $is_favorite
 * @property int $sort_order
 */
class UserShortcut extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'path',
        'is_favorite',
        'sort_order',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
