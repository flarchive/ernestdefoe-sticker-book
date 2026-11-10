<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Packs;
use ErnestDefoe\StickerBook\Settings;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/sticker-book/open: open the member's next pack. Takes nothing
 * from the browser: which pack, and what is in it, is the server's choice.
 */
class OpenController extends Endpoint
{
    public function __construct(private readonly Packs $packs, private readonly Catalog $catalog)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::COLLECT);
        $this->limit($actor, 'open', 20, 400);

        $result = $this->packs->open($actor);
        $sets = array_values(array_filter($this->catalog->all()['sets'], fn ($set) => in_array($set['id'], $result['completed'], true)));

        return [
            'cards' => $result['cards'],
            'source' => $result['source'],
            'completed' => array_map(fn ($set) => ['id' => $set['id'], 'name' => $set['name']], $sets),
            'status' => $this->packs->status($actor),
        ];
    }
}
