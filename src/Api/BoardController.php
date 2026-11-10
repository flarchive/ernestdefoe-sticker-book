<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Collection;
use ErnestDefoe\StickerBook\Settings;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/sticker-book/board: the members with the most different stickers. */
class BoardController extends Endpoint
{
    public function __construct(private readonly Collection $collection, private readonly Catalog $catalog)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertCan(Settings::VIEW);

        $rows = $this->collection->collectors()->where('unique_count', '>', 0)
            ->orderByDesc('unique_count')->orderBy('last_opened_at')->limit(15)->get(['user_id', 'unique_count']);
        $users = Presenter::visible($actor, $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $visible = array_column($users, 'id');

        return [
            'board' => $rows->filter(fn ($row) => in_array((int) $row->user_id, $visible, true))
                ->map(fn ($row) => [(int) $row->user_id, (int) $row->unique_count])->values()->all(),
            'total' => count($this->catalog->liveStickers()),
            'users' => $users,
        ];
    }
}
