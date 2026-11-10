<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $set_id
 * @property string $name
 * @property int $rarity
 * @property array<string, mixed>|null $image
 * @property int $position
 * @property-read StickerSet $set
 */
class Sticker extends AbstractModel
{
    protected $table = 'stickers';

    public $timestamps = true;

    protected $casts = ['set_id' => 'integer', 'rarity' => 'integer', 'position' => 'integer', 'image' => 'array'];

    protected static function booted(): void
    {
        // The foreign keys remove copies and reactions too, where the database
        // enforces them; either way the owners' distinct counts are redone.
        static::deleting(function (self $sticker) {
            $db = $sticker->getConnection();
            $owners = $db->table('sticker_owned')->where('sticker_id', $sticker->id)->pluck('user_id')->all();
            $db->table('sticker_owned')->where('sticker_id', $sticker->id)->delete();
            $db->table('sticker_reactions')->where('sticker_id', $sticker->id)->delete();

            foreach (array_chunk($owners, 500) as $chunk) {
                $counts = $db->table('sticker_owned')->whereIn('user_id', $chunk)->groupBy('user_id')->selectRaw('user_id, COUNT(*) as n')->pluck('n', 'user_id');
                foreach ($chunk as $userId) {
                    $db->table('sticker_collectors')->where('user_id', $userId)->update(['unique_count' => (int) ($counts[$userId] ?? 0)]);
                }
            }
        });
    }

    /** @return BelongsTo<StickerSet, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(StickerSet::class, 'set_id');
    }
}
