<?php

namespace ErnestDefoe\StickerBook\Admin;

use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Packs;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/sticker-book/gift {username, count}: give a member packs (a contest prize, an event). */
class GiftController extends Endpoint
{
    public function __construct(private readonly Packs $packs)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();
        $body = $this->body($request);

        $user = User::query()->where('username', (string) ($body['username'] ?? ''))->first();
        if (! $user) {
            throw new StickerException('sticker_user_not_found', 404);
        }
        $count = max(1, min(50, (int) ($body['count'] ?? 1)));
        $this->packs->gift((int) $user->id, $count);

        return ['username' => $user->username, 'count' => $count];
    }
}
