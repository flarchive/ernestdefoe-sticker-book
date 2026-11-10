<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Collection;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Http\SlugManager;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/sticker-book/album/{user}: a member's album, set by set. Stickers
 * they have come with their art and count; stickers they don't come with
 * the silhouette only (🚨 never the art's address).
 */
class AlbumController extends Endpoint
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Collection $collection,
        private readonly SlugManager $slugs,
        private readonly ConnectionInterface $db,
    ) {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertCan(Settings::VIEW);

        $slug = (string) Arr::get($request->getQueryParams(), 'user');
        $user = $this->slugs->forResource(User::class)->fromSlug($slug, $actor);

        $counts = $this->collection->countsFor((int) $user->id);
        $completed = $this->db->table('sticker_completions')->where('user_id', $user->id)->pluck('completed_at', 'set_id');
        $stickers = $this->catalog->all()['stickers'];

        $sets = [];
        foreach ($this->catalog->liveSets() as $set) {
            $rows = [];
            foreach ($set['stickers'] as $id) {
                $s = $stickers[$id];
                $n = $counts[$id] ?? 0;
                $rows[] = $n
                    ? ['id' => $id, 'name' => $s['name'], 'rarity' => $s['rarity'], 'count' => $n, 'small' => $s['small'], 'full' => $s['full']]
                    : ['id' => $id, 'name' => $s['name'], 'rarity' => $s['rarity'], 'count' => 0, 'sil' => $s['sil']];
            }
            $sets[] = [
                'id' => $set['id'],
                'name' => $set['name'],
                'description' => $set['description'],
                'completedAt' => isset($completed[$set['id']]) ? (string) $completed[$set['id']] : null,
                'stickers' => $rows,
            ];
        }

        $collector = $this->collection->collectors()->where('user_id', $user->id)->first();

        return [
            'user' => Presenter::users([$user])[0],
            'sets' => $sets,
            'stats' => [
                'unique' => count(array_intersect_key($counts, $this->catalog->liveStickers())),
                'total' => count($this->catalog->liveStickers()),
                'completed' => count(array_filter($sets, fn ($set) => $set['completedAt'] !== null)),
                'opened' => (int) ($collector->packs_opened ?? 0),
            ],
        ];
    }
}
