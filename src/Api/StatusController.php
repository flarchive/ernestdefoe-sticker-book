<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Packs;
use ErnestDefoe\StickerBook\Settings;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/sticker-book/me: the member's packs, the odds and when the next free pack comes. */
class StatusController extends Endpoint
{
    public function __construct(private readonly Packs $packs, private readonly Settings $settings)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::COLLECT);

        return [...$this->packs->status($actor), 'odds' => $this->settings->odds(), 'packSize' => $this->settings->packSize(), 'pity' => $this->settings->pity()];
    }
}
