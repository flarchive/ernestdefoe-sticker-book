<?php

namespace ErnestDefoe\StickerBook;

use ErnestDefoe\StickerBook\Storage\Art;
use Illuminate\Contracts\Cache\Repository;

/**
 * Every set and sticker, as plain arrays: one cache entry, rebuilt (two
 * queries) after the admin changes anything. Albums, packs, posts and
 * profiles all read from here, so none of them queries the sets.
 *
 * 🚨 `full` (the art) is only ever sent for stickers the viewer may see in
 * full: someone's album shows missing stickers by `sil` alone.
 */
class Catalog
{
    private const KEY = 'ernestdefoe-sticker-book.catalog';

    /** @var array{sets: list<array<string, mixed>>, stickers: array<int, array<string, mixed>>}|null */
    private ?array $loaded = null;

    public function __construct(private readonly Repository $cache, private readonly Art $art)
    {
    }

    /** @return array{sets: list<array<string, mixed>>, stickers: array<int, array<string, mixed>>} */
    public function all(): array
    {
        return $this->loaded ??= $this->cache->rememberForever(self::KEY, fn () => $this->build());
    }

    public function forget(): void
    {
        $this->loaded = null;
        $this->cache->forget(self::KEY);
    }

    /** @return array<string, mixed>|null */
    public function sticker(int $id): ?array
    {
        return $this->all()['stickers'][$id] ?? null;
    }

    /** @return list<array<string, mixed>> Sets members can see, in order. */
    public function liveSets(): array
    {
        return array_values(array_filter($this->all()['sets'], fn ($set) => ! $set['hidden'] && $set['stickers']));
    }

    /** @return array<int, array<string, mixed>> Stickers that can come out of a pack, by id. */
    public function liveStickers(): array
    {
        $out = [];
        $stickers = $this->all()['stickers'];
        foreach ($this->liveSets() as $set) {
            foreach ($set['stickers'] as $id) {
                $out[$id] = $stickers[$id];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> A sticker as anyone may see it once they have it. */
    public function card(int $id): ?array
    {
        $s = $this->sticker($id);

        return $s ? ['id' => $s['id'], 'setId' => $s['setId'], 'name' => $s['name'], 'rarity' => $s['rarity'], 'full' => $s['full'], 'small' => $s['small']] : null;
    }

    /** @return array{sets: list<array<string, mixed>>, stickers: array<int, array<string, mixed>>} */
    private function build(): array
    {
        $sets = StickerSet::query()->orderBy('position')->orderBy('id')->get();
        $stickers = Sticker::query()->orderBy('position')->orderBy('id')->get()->groupBy('set_id');

        $out = ['sets' => [], 'stickers' => []];
        foreach ($sets as $set) {
            $ids = [];
            foreach ($stickers[$set->id] ?? [] as $sticker) {
                if (! $sticker->image) {
                    continue;
                }
                $ids[] = $sticker->id;
                $out['stickers'][$sticker->id] = [
                    'id' => $sticker->id,
                    'setId' => $set->id,
                    'name' => $sticker->name,
                    'rarity' => $sticker->rarity,
                    'full' => $this->art->url($sticker->image, 'full'),
                    'small' => $this->art->url($sticker->image, 'small'),
                    'sil' => $this->art->url($sticker->image, 'sil'),
                ];
            }
            $out['sets'][] = [
                'id' => $set->id,
                'name' => $set->name,
                'description' => $set->description,
                'badgeId' => $set->badge_id,
                'hidden' => $set->is_hidden,
                'stickers' => $ids,
            ];
        }

        return $out;
    }
}
