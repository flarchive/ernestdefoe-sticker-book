<?php

namespace ErnestDefoe\StickerBook\Api;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Collection;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Reactions;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/sticker-book/react {post, sticker}: put one of the member's own
 * stickers on a post they can see (or take theirs off with sticker: null).
 */
class ReactController extends Endpoint
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Catalog $catalog,
        private readonly Collection $collection,
        private readonly Reactions $reactions,
        private readonly ConnectionInterface $db,
    ) {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::REACT);
        if (! $this->settings->reactions()) {
            throw new StickerException('sticker_reactions_off', 403);
        }

        $body = $this->body($request);
        $post = is_numeric($body['post'] ?? null) ? Post::query()->whereVisibleTo($actor)->find((int) $body['post']) : null;
        if (! $post instanceof CommentPost || $post->hidden_at) {
            throw new StickerException('sticker_post_not_found', 404);
        }

        $this->limit($actor, 'react', 30, 500);
        $sticker = $body['sticker'] ?? null;

        if ($sticker === null) {
            $this->db->table('sticker_reactions')->where('post_id', $post->id)->where('user_id', $actor->id)->delete();
        } else {
            $id = is_numeric($sticker) ? (int) $sticker : 0;
            if (! isset($this->catalog->liveStickers()[$id])
                || ! $this->collection->owned()->where('user_id', $actor->id)->where('sticker_id', $id)->where('count', '>', 0)->exists()) {
                throw new StickerException('sticker_not_yours');
            }
            $this->db->table('sticker_reactions')->upsert(
                [['post_id' => $post->id, 'user_id' => $actor->id, 'sticker_id' => $id, 'created_at' => Carbon::now()]],
                ['post_id', 'user_id'],
                ['sticker_id', 'created_at'],
            );
        }

        return ['reactions' => $this->reactions->load([(int) $post->id], (int) $actor->id)[$post->id] ?? []];
    }
}
