<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Collection;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Settings;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/sticker-book/spares?sticker=ID: members with a spare of a sticker, to trade with. */
class SparesController extends Endpoint
{
    public function __construct(private readonly Collection $collection, private readonly Catalog $catalog)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::TRADE);

        $id = (int) Arr::get($request->getQueryParams(), 'sticker');
        if (! isset($this->catalog->liveStickers()[$id])) {
            throw new StickerException('sticker_not_found', 404);
        }

        $rows = $this->collection->owned()->where('sticker_id', $id)->where('count', '>', 1)->where('user_id', '<>', $actor->id)
            ->orderByDesc('count')->limit(24)->pluck('count', 'user_id');
        $users = Presenter::visible($actor, $rows->keys()->map(fn ($id) => (int) $id)->all());

        return [
            'spares' => array_map(fn ($user) => [$user['id'], (int) $rows[$user['id']] - 1], $users),
            'users' => $users,
        ];
    }
}
