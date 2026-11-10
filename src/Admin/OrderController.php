<?php

namespace ErnestDefoe\StickerBook\Admin;

use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Sticker;
use ErnestDefoe\StickerBook\StickerSet;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/sticker-book/order {sets: [ids]} or {set, stickers: [ids]}: the order albums show them in. */
class OrderController extends Endpoint
{
    public function __construct(private readonly Catalog $catalog)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();
        $body = $this->body($request);

        foreach (array_values((array) ($body['sets'] ?? [])) as $i => $id) {
            StickerSet::query()->whereKey((int) $id)->update(['position' => $i]);
        }
        foreach (array_values((array) ($body['stickers'] ?? [])) as $i => $id) {
            Sticker::query()->whereKey((int) $id)->where('set_id', (int) ($body['set'] ?? 0))->update(['position' => $i]);
        }
        $this->catalog->forget();

        return ['ok' => true];
    }
}
