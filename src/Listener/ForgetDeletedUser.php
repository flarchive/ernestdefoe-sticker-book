<?php

namespace ErnestDefoe\StickerBook\Listener;

use Flarum\User\Event\Deleted;
use Illuminate\Database\ConnectionInterface;

/** The foreign keys do this too, where the database enforces them (SQLite may not). */
class ForgetDeletedUser
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function handle(Deleted $event): void
    {
        $this->forget((int) $event->user->id);
    }

    public function forget(int $id): void
    {
        foreach (['sticker_owned', 'sticker_collectors', 'sticker_packs', 'sticker_completions', 'sticker_reactions', 'sticker_like_credits'] as $table) {
            $this->db->table($table)->where('user_id', $id)->delete();
        }
        $this->db->table('sticker_trades')->where('from_user_id', $id)->orWhere('to_user_id', $id)->delete();
    }
}
