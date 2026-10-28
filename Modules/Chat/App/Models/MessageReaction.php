<?php

namespace Modules\Chat\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    protected $table = 'message_reactions';

    /** @var list<string> */
    public const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡'];

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function canonical(string $emoji): ?string
    {
        $needle = self::stripVariation(trim($emoji));
        if ($needle === '') {
            return null;
        }

        foreach (self::EMOJIS as $allowed) {
            if (self::stripVariation($allowed) === $needle) {
                return $allowed;
            }
        }

        return null;
    }

    private static function stripVariation(string $emoji): string
    {
        return str_replace("\u{FE0F}", '', $emoji);
    }
}
