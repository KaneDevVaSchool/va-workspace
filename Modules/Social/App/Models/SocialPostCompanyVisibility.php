<?php

namespace Modules\Social\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Company;

/**
 * @property int $id
 * @property int $post_id
 * @property int $company_id
 */
class SocialPostCompanyVisibility extends Model
{
    protected $table = 'social_post_company_visibility';

    protected $fillable = [
        'post_id',
        'company_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
