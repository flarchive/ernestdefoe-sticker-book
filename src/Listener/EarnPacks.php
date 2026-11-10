<?php

namespace ErnestDefoe\StickerBook\Listener;

use ErnestDefoe\StickerBook\Collection;
use ErnestDefoe\StickerBook\Packs;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Post\Event\Posted;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Extra packs for taking part, all within the daily cap (Packs::earn):
 * the first post of the day (once it is approved), and every N likes a
 * member's posts receive. 🚨 Each member's like on a post counts once, ever
 * (sticker_like_credits, kept on unlike): liking, unliking and liking again
 * earns nothing.
 */
class EarnPacks
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Packs $packs,
        private readonly Collection $collection,
        private readonly ConnectionInterface $db,
    ) {
    }

    public function posted(Posted $event): void
    {
        $user = $event->post->user;
        // A post waiting for approval (flarum/approval's column, when it is there) doesn't count yet.
        $attributes = $event->post->getAttributes();
        if ((array_key_exists('is_approved', $attributes) && ! $attributes['is_approved']) || ! $user || ! $this->settings->firstPost() || ! $this->collects($user)) {
            return;
        }

        $today = Packs::today();
        $this->collection->collectors()->insertOrIgnore(['user_id' => $user->id]);
        $first = $this->collection->collectors()->where('user_id', $user->id)
            ->where(fn (Builder $q) => $q->whereNull('post_on')->orWhere('post_on', '<', $today))
            ->update(['post_on' => $today]);

        if ($first === 1) {
            $this->packs->earn((int) $user->id, 'post');
        }
    }

    public function liked(Post $post, User $liker): void
    {
        $owner = $post->user;
        $every = $this->settings->likesPerPack();
        if (! $every || ! $owner || $owner->id === $liker->id || ! $this->collects($owner)) {
            return;
        }

        // A like this member already gave this post (and took back) has had its turn.
        if ($this->db->table('sticker_like_credits')->insertOrIgnore(['post_id' => $post->id, 'user_id' => $liker->id]) !== 1) {
            return;
        }

        $this->collection->collectors()->insertOrIgnore(['user_id' => $owner->id]);
        $this->collection->collectors()->where('user_id', $owner->id)->increment('like_progress');
        // Only the request that brings the count to N spends it: a guarded UPDATE.
        if ($this->collection->collectors()->where('user_id', $owner->id)->where('like_progress', '>=', $every)->decrement('like_progress', $every) === 1) {
            $this->packs->earn((int) $owner->id, 'likes');
        }
    }

    private function collects(User $user): bool
    {
        return $user->hasPermission(Settings::COLLECT) && (bool) resolve(\ErnestDefoe\StickerBook\Catalog::class)->liveStickers();
    }
}
