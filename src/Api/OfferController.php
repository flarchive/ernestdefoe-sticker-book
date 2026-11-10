<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Settings;
use ErnestDefoe\StickerBook\Trades;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/sticker-book/trades {to, give: [[id, n]], take: [[id, n]], message}: offer spares to another member. */
class OfferController extends Endpoint
{
    public function __construct(private readonly Trades $trades, private readonly Catalog $catalog)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::TRADE);

        $body = $this->body($request);
        $to = is_numeric($body['to'] ?? null) ? User::query()->whereVisibleTo($actor)->find((int) $body['to']) : null;
        if (! $to instanceof User) {
            throw new StickerException('sticker_trade_unavailable', 404);
        }
        $message = isset($body['message']) && is_string($body['message']) ? $body['message'] : null;

        $this->limit($actor, 'offer', 6, 60);
        $trade = $this->trades->offer($actor, $to, $body['give'] ?? [], $body['take'] ?? [], $message);

        return TradesController::present($actor, [$trade], $this->catalog);
    }
}
