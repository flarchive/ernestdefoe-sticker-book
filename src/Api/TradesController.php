<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Settings;
use ErnestDefoe\StickerBook\Trade;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/sticker-book/trades: the member's offers, waiting ones first,
 * then the 20 most recent closed ones. Only trades they are part of.
 */
class TradesController extends Endpoint
{
    public function __construct(private readonly Catalog $catalog)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::TRADE);

        $mine = fn (Builder $q) => $q->where('from_user_id', $actor->id)->orWhere('to_user_id', $actor->id);
        $pending = Trade::query()->where($mine)->where('status', Trade::PENDING)->orderByDesc('id')->limit(50)->get();
        $closed = Trade::query()->where($mine)->where('status', '<>', Trade::PENDING)->orderByDesc('id')->limit(20)->get();

        return self::present($actor, $pending->concat($closed)->all(), $this->catalog);
    }

    /**
     * Trades with the stickers in them (their art: both members are looking
     * at what is on offer) and the members.
     *
     * @param list<Trade> $trades
     * @return array<string, mixed>
     */
    public static function present(User $actor, array $trades, Catalog $catalog): array
    {
        $stickers = [];
        $users = [];
        $rows = [];
        foreach ($trades as $trade) {
            foreach ([...$trade->give, ...$trade->take] as [$id]) {
                $stickers[$id] ??= $catalog->card($id);
            }
            $users[] = $trade->from_user_id;
            $users[] = $trade->to_user_id;
            $rows[] = [
                'id' => $trade->id,
                'from' => $trade->from_user_id,
                'to' => $trade->to_user_id,
                'status' => $trade->effectiveStatus(),
                'give' => $trade->give,
                'take' => $trade->take,
                'message' => $trade->message,
                'createdAt' => $trade->created_at->toIso8601String(),
                'decidedAt' => $trade->decided_at?->toIso8601String(),
            ];
        }

        return ['trades' => $rows, 'stickers' => array_values(array_filter($stickers)), 'users' => Presenter::visible($actor, $users)];
    }
}
