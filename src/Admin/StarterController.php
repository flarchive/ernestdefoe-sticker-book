<?php

namespace ErnestDefoe\StickerBook\Admin;

use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Starter;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/sticker-book/starter: add the starter sets (hidden, for the admin to look over and show). */
class StarterController extends Endpoint
{
    public function __construct(private readonly Starter $starter)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        return ['added' => $this->starter->install(true)];
    }
}
