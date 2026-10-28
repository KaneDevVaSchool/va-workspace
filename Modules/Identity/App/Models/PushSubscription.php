<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription Web Push của một trình duyệt.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint_hash
 * @property string $endpoint
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property int $failed_count
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint_hash',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'failed_count',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
