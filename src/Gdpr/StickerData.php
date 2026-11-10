<?php

namespace ErnestDefoe\StickerBook\Gdpr;

use ErnestDefoe\StickerBook\Listener\ForgetDeletedUser;
use Flarum\Gdpr\Data\Type;
use Illuminate\Database\ConnectionInterface;

/** A member's export: their stickers, packs, sets completed, trades and reactions. Erasing removes them all. */
class StickerData extends Type
{
    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-sticker-book.lib.gdpr.export');
    }

    public static function anonymizeDescription(): string
    {
        return self::deleteDescription();
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-sticker-book.lib.gdpr.delete');
    }

    public function export(): ?array
    {
        $db = resolve(ConnectionInterface::class);
        $id = $this->user->id;
        $rows = [
            'stickers' => $db->table('sticker_owned')->join('stickers', 'stickers.id', '=', 'sticker_owned.sticker_id')
                ->where('sticker_owned.user_id', $id)->get(['stickers.name', 'sticker_owned.count', 'sticker_owned.first_at'])->toArray(),
            'packs' => $db->table('sticker_packs')->where('user_id', $id)->get(['source', 'contents', 'created_at', 'opened_at'])->toArray(),
            'sets_completed' => $db->table('sticker_completions')->where('user_id', $id)->get(['set_id', 'completed_at'])->toArray(),
            'trades' => $db->table('sticker_trades')->where('from_user_id', $id)->orWhere('to_user_id', $id)
                ->get(['from_user_id', 'to_user_id', 'status', 'give', 'take', 'message', 'created_at', 'decided_at'])->toArray(),
            'reactions' => $db->table('sticker_reactions')->where('user_id', $id)->get(['post_id', 'sticker_id', 'created_at'])->toArray(),
        ];

        return [['sticker-book/stickers.json' => $this->encodeForExport($rows)]];
    }

    public function anonymize(): void
    {
        $this->delete();
    }

    public function delete(): void
    {
        resolve(ForgetDeletedUser::class)->forget((int) $this->user->id);
    }
}
