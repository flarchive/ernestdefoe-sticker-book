<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property string $status
 * @property list<array{0: int, 1: int}> $give
 * @property list<array{0: int, 1: int}> $take
 * @property string|null $message
 * @property Carbon $created_at
 * @property Carbon|null $decided_at
 */
class Trade extends AbstractModel
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';

    /** A pending offer older than this has expired. */
    public const LIFETIME_DAYS = 14;

    protected $table = 'sticker_trades';

    public $timestamps = false;

    protected $casts = [
        'from_user_id' => 'integer',
        'to_user_id' => 'integer',
        'give' => 'array',
        'take' => 'array',
        'created_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /** The status as members see it: a pending offer past its lifetime has expired. */
    public function effectiveStatus(): string
    {
        return $this->status === self::PENDING && $this->isExpired() ? self::EXPIRED : $this->status;
    }

    public function isExpired(): bool
    {
        return $this->created_at->lt(Carbon::now()->subDays(self::LIFETIME_DAYS));
    }
}
