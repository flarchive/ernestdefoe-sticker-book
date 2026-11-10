<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Settings;
use ErnestDefoe\StickerBook\Trade;
use ErnestDefoe\StickerBook\Trades;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/sticker-book/trades/{id}/{action}: accept or decline (the
 * recipient), cancel (the sender). Trades::accept does the swap.
 */
class TradeActionController extends Endpoint
{
    public function __construct(private readonly Trades $trades, private readonly Catalog $catalog)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::TRADE);

        $trade = Trade::query()->find((int) Arr::get($request->getQueryParams(), 'id'));
        // Someone else's trade is "not found", whatever its id.
        if (! $trade || ($trade->from_user_id !== $actor->id && $trade->to_user_id !== $actor->id)) {
            throw new StickerException('sticker_trade_not_found', 404);
        }

        $action = (string) Arr::get($request->getQueryParams(), 'action');
        $this->limit($actor, 'trade-action', 20, 300);
        $trade = match ($action) {
            'accept' => $this->trades->accept($trade, $actor),
            'decline' => $trade->to_user_id === $actor->id ? $this->trades->close($trade, $actor) : throw new StickerException('sticker_trade_not_found', 404),
            'cancel' => $trade->from_user_id === $actor->id ? $this->trades->close($trade, $actor) : throw new StickerException('sticker_trade_not_found', 404),
            default => throw new StickerException('sticker_trade_not_found', 404),
        };

        return TradesController::present($actor, [$trade], $this->catalog);
    }
}
