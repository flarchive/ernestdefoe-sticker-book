<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Notification\TradeBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Trades of spares between two members.
 *
 * 🚨 Nothing is held while an offer waits, so an offer can't lock up a
 * sticker. Accepting does everything in one transaction: both members'
 * collector rows are locked (in id order), the trade moves from pending to
 * accepted in a guarded UPDATE (it is accepted once), and every spare leaves
 * its owner in a guarded UPDATE that keeps at least one copy. If either side
 * no longer has the spares, the whole transaction rolls back and the offer
 * is marked failed: nothing is ever created or lost.
 */
class Trades
{
    /** Different stickers per side. */
    public const MAX_ITEMS = 8;

    /** Copies of one sticker in one offer. */
    public const MAX_COPIES = 20;

    /** Offers a member can have waiting at once. */
    public const MAX_PENDING = 10;

    /** Offers waiting from one member to the same member. */
    public const MAX_PENDING_TO_ONE = 3;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Catalog $catalog,
        private readonly Collection $collection,
        private readonly NotificationSyncer $notifications,
        private readonly Settings $settings,
    ) {
    }

    /**
     * Accounts younger than the admin's minimum can't offer or accept trades,
     * so a batch of fresh accounts can't funnel their packs into one album.
     */
    public function assertOldEnough(User $user): void
    {
        $days = $this->settings->tradeMinAge();
        if ($days > 0 && $user->joined_at && $user->joined_at->gt(Carbon::now()->subDays($days))) {
            throw new StickerException('sticker_trade_too_new', 403);
        }
    }

    /**
     * @param mixed $give from the request: a list of [sticker id, count]
     * @param mixed $take
     */
    public function offer(User $from, User $to, mixed $give, mixed $take, ?string $message): Trade
    {
        if ($from->id === $to->id) {
            throw new StickerException('sticker_trade_self');
        }
        $this->assertOldEnough($from);
        // A suspended member has no permissions (flarum/suspend), so this covers them too.
        if (! $to->hasPermission(Settings::TRADE)) {
            throw new StickerException('sticker_trade_unavailable');
        }

        $give = $this->items($give);
        $take = $this->items($take);
        if (! $give) {
            throw new StickerException('sticker_trade_empty');
        }
        if (array_intersect(array_column($give, 0), array_column($take, 0))) {
            throw new StickerException('sticker_trade_same');
        }

        $pending = Trade::query()->where('from_user_id', $from->id)->where('status', Trade::PENDING)
            ->where('created_at', '>=', Carbon::now()->subDays(Trade::LIFETIME_DAYS))->pluck('to_user_id');
        if ($pending->count() >= self::MAX_PENDING || $pending->filter(fn ($id) => (int) $id === $to->id)->count() >= self::MAX_PENDING_TO_ONE) {
            throw new StickerException('sticker_trade_too_many');
        }

        // Checked now so a member hears it at once; checked again when accepted.
        $this->assertSpares((int) $from->id, $give, 'sticker_trade_no_spares');
        $this->assertSpares((int) $to->id, $take, 'sticker_trade_they_have_no_spares');

        $trade = new Trade();
        $trade->from_user_id = $from->id;
        $trade->to_user_id = $to->id;
        $trade->status = Trade::PENDING;
        $trade->give = $give;
        $trade->take = $take;
        $trade->message = $message !== null && trim($message) !== '' ? mb_substr(trim($message), 0, 200) : null;
        $trade->created_at = Carbon::now();
        $trade->save();

        $this->notifications->sync(new TradeBlueprint($trade, $from, 'offered'), [$to]);

        return $trade;
    }

    public function accept(Trade $trade, User $actor): Trade
    {
        if ($trade->to_user_id !== $actor->id) {
            throw new StickerException('sticker_trade_not_found', 404);
        }
        $this->assertOldEnough($actor);

        $failed = false;
        $completed = [];
        try {
            $completed = $this->db->transaction(function () use ($trade) {
                $this->collection->lock([$trade->from_user_id, $trade->to_user_id]);

                $moved = Trade::query()->whereKey($trade->id)->where('status', Trade::PENDING)
                    ->where('created_at', '>=', Carbon::now()->subDays(Trade::LIFETIME_DAYS))
                    ->update(['status' => Trade::ACCEPTED, 'decided_at' => Carbon::now()]);
                if ($moved !== 1) {
                    throw new StickerException('sticker_trade_closed');
                }

                $live = $this->catalog->liveStickers();
                foreach ([[$trade->from_user_id, $trade->give], [$trade->to_user_id, $trade->take]] as [$owner, $items]) {
                    foreach ($items as [$id, $n]) {
                        if (! isset($live[$id]) || ! $this->collection->takeSpares($owner, $id, $n)) {
                            throw new StickerException('sticker_trade_spares_gone');
                        }
                    }
                }

                $this->collection->add($trade->to_user_id, $this->counts($trade->give));
                $this->collection->add($trade->from_user_id, $this->counts($trade->take));

                return [
                    $trade->to_user_id => $this->collection->settle($trade->to_user_id, array_column($trade->give, 0)),
                    $trade->from_user_id => $this->collection->settle($trade->from_user_id, array_column($trade->take, 0)),
                ];
            });
        } catch (StickerException $e) {
            if ($e->errorCode !== 'sticker_trade_spares_gone') {
                throw $e;
            }
            $failed = true;
        }

        if ($failed) {
            // Rolled back above; the offer can't go through, so it closes.
            Trade::query()->whereKey($trade->id)->where('status', Trade::PENDING)->update(['status' => Trade::FAILED, 'decided_at' => Carbon::now()]);
            $trade->refresh();
            $this->notifications->sync(new TradeBlueprint($trade, $actor, 'failed'), [$this->user($trade->from_user_id)]);

            throw new StickerException('sticker_trade_spares_gone');
        }

        $trade->refresh();
        $from = $this->user($trade->from_user_id);
        $this->collection->awardBadges($actor, $completed[$actor->id] ?? []);
        $this->collection->awardBadges($from, $completed[$from->id] ?? []);
        $this->notifications->sync(new TradeBlueprint($trade, $actor, 'accepted'), [$from]);

        return $trade;
    }

    /** The recipient declines, or the sender takes the offer back. */
    public function close(Trade $trade, User $actor): Trade
    {
        $status = match ($actor->id) {
            $trade->to_user_id => Trade::DECLINED,
            $trade->from_user_id => Trade::CANCELLED,
            default => throw new StickerException('sticker_trade_not_found', 404),
        };

        if (Trade::query()->whereKey($trade->id)->where('status', Trade::PENDING)->update(['status' => $status, 'decided_at' => Carbon::now()]) !== 1) {
            throw new StickerException('sticker_trade_closed');
        }
        $trade->refresh();

        if ($status === Trade::DECLINED) {
            $this->notifications->sync(new TradeBlueprint($trade, $actor, 'declined'), [$this->user($trade->from_user_id)]);
        } else {
            // The offer is gone: so is the recipient's notification about it.
            $this->notifications->sync(new TradeBlueprint($trade, $actor, 'offered'), []);
        }

        return $trade;
    }

    /**
     * A side of an offer from the request, cleaned: whole numbers only,
     * stickers that exist in a live set, each once, within the limits.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function items(mixed $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }
        if (! is_array($raw) || count($raw) > self::MAX_ITEMS) {
            throw new StickerException('sticker_trade_invalid');
        }

        $live = $this->catalog->liveStickers();
        $items = [];
        foreach ($raw as $item) {
            $id = is_array($item) ? ($item[0] ?? $item['sticker'] ?? null) : null;
            $n = is_array($item) ? ($item[1] ?? $item['count'] ?? 1) : null;
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw new StickerException('sticker_trade_invalid');
            }
            if (! is_int($n) && ! (is_string($n) && ctype_digit($n))) {
                throw new StickerException('sticker_trade_invalid');
            }
            $id = (int) $id;
            $n = (int) $n;
            if (! isset($live[$id]) || $n < 1 || $n > self::MAX_COPIES || isset($items[$id])) {
                throw new StickerException('sticker_trade_invalid');
            }
            $items[$id] = [$id, $n];
        }

        return array_values($items);
    }

    /** @param list<array{0: int, 1: int}> $items */
    private function assertSpares(int $userId, array $items, string $code): void
    {
        if (! $items) {
            return;
        }
        $counts = $this->collection->owned()->where('user_id', $userId)->whereIn('sticker_id', array_column($items, 0))->pluck('count', 'sticker_id');
        foreach ($items as [$id, $n]) {
            if ((int) ($counts[$id] ?? 0) <= $n) {
                throw new StickerException($code);
            }
        }
    }

    /**
     * @param list<array{0: int, 1: int}> $items
     * @return array<int, int>
     */
    private function counts(array $items): array
    {
        $out = [];
        foreach ($items as [$id, $n]) {
            $out[$id] = $n;
        }

        return $out;
    }

    private function user(int $id): User
    {
        return User::query()->findOrFail($id);
    }
}
