<?php

namespace Modules\Identity\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ledger idempotency cho webhook VA-HRM — bookkeeping kỹ thuật thuần
 * (không phải domain model), được miễn trừ pattern Repository. Xem
 * Modules/Identity/App/Hrm/Services/HrmWebhookDispatcher.php.
 *
 * @property int $id
 * @property string $delivery_id
 * @property string $event
 * @property \Illuminate\Support\Carbon $processed_at
 */
class HrmWebhookDelivery extends Model
{
    protected $table = 'hrm_webhook_deliveries';

    protected $fillable = [
        'delivery_id',
        'event',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
