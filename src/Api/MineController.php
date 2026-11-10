<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Collection;
use ErnestDefoe\StickerBook\Settings;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/sticker-book/mine: the member's own stickers, rarest first, for the reaction picker. */
class MineController extends Endpoint
{
    public function __construct(private readonly Catalog $catalog, private readonly Collection $collection)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::REACT);

        $live = $this->catalog->liveStickers();
        $cards = [];
        foreach ($this->collection->countsFor((int) $actor->id) as $id => $n) {
            if (isset($live[$id])) {
                $cards[] = [...($this->catalog->card($id) ?? []), 'count' => $n];
            }
        }
        usort($cards, fn ($a, $b) => [$b['rarity'], $a['name']] <=> [$a['rarity'], $b['name']]);

        return ['stickers' => $cards];
    }
}
