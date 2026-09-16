<?php

namespace Modules\Social\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property string $url
 * @property string $provider
 * @property string|null $external_id
 * @property string|null $title
 * @property string|null $thumbnail_url
 * @property string|null $embed_url
 * @property bool $is_live
 * @property int $position
 */
class SocialLinkPreview extends Model
{
    protected $table = 'social_link_previews';

    public const PROVIDER_YOUTUBE = 'youtube';

    public const PROVIDER_FACEBOOK = 'facebook';

    public const PROVIDER_TIKTOK = 'tiktok';

    protected $fillable = [
        'post_id',
        'url',
        'provider',
        'external_id',
        'title',
        'thumbnail_url',
        'embed_url',
        'is_live',
        'position',
    ];

    protected $casts = [
        'is_live' => 'boolean',
        'position' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }
}
