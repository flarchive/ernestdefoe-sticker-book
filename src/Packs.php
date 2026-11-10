<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Exception\StickerException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Packs: earned, held, opened. 🚨 Everything a pack contains is decided here,
 * on the server, with random_int (the system CSPRNG); the browser only asks
 * to open one and is told what was inside.
 *
 * - The daily pack is claimed by opening it: one guarded UPDATE on the
 *   member's `daily_on`, so two taps can't claim it twice.
 * - Extra packs (first post of the day, likes received) count against a
 *   daily cap in one guarded UPDATE as well.
 * - Opening marks the pack opened in a guarded UPDATE (it opens once), rolls
 *   the stickers and adds them, inside one transaction with the member's
 *   collector row locked.
 */
class Packs
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Settings $settings,
        private readonly Catalog $catalog,
        private readonly Collection $collection,
    ) {
    }

    public static function today(): string
    {
        return Carbon::now('UTC')->toDateString();
    }

    public function packs(): Builder
    {
        return $this->db->table('sticker_packs');
    }

    /**
     * What the member's pack button shows. One query for the collector row,
     * one count of unopened packs.
     *
     * @return array<string, mixed>
     */
    public function status(User $user): array
    {
        $row = $this->collection->collectors()->where('user_id', $user->id)->first();
        $daily = ! $row || ! $row->daily_on || (string) $row->daily_on < self::today();
        $pity = $this->settings->pity();

        return [
            'packs' => $this->packs()->where('user_id', $user->id)->whereNull('opened_at')->count() + ($daily ? 1 : 0),
            'daily' => $daily,
            'nextDaily' => Carbon::now('UTC')->addDay()->startOfDay()->toIso8601String(),
            'pityLeft' => $pity ? max(1, $pity - (int) ($row->pity ?? 0)) : null,
            'unique' => (int) ($row->unique_count ?? 0),
            'opened' => (int) ($row->packs_opened ?? 0),
        ];
    }

    /**
     * Give a member an extra pack for taking part, within the daily cap and
     * the unopened limit.
     */
    public function earn(int $userId, string $source): bool
    {
        $cap = $this->settings->extraCap();
        if ($cap < 1) {
            return false;
        }

        return $this->db->transaction(function () use ($userId, $source, $cap) {
            $this->collection->lock([$userId]);
            if ($this->packs()->where('user_id', $userId)->whereNull('opened_at')->count() >= Settings::MAX_UNOPENED) {
                return false;
            }

            $today = self::today();
            $collectors = fn () => $this->collection->collectors()->where('user_id', $userId);
            // Today's count goes up while under the cap; a new day starts it again at one.
            $counted = $collectors()->where('extra_on', $today)->where('extra_count', '<', $cap)->increment('extra_count') === 1
                || $collectors()->where(fn (Builder $q) => $q->whereNull('extra_on')->orWhere('extra_on', '<', $today))->update(['extra_on' => $today, 'extra_count' => 1]) === 1;
            if (! $counted) {
                return false;
            }

            $this->packs()->insert(['user_id' => $userId, 'source' => $source, 'created_at' => Carbon::now()]);

            return true;
        });
    }

    /** A pack from an admin (no cap, no daily count). */
    public function gift(int $userId, int $count = 1): void
    {
        $now = Carbon::now();
        $this->packs()->insert(array_fill(0, max(1, min(50, $count)), ['user_id' => $userId, 'source' => 'gift', 'created_at' => $now]));
    }

    /**
     * Open the member's next pack: today's free one first, then the oldest
     * earned one.
     *
     * @return array{cards: list<array<string, mixed>>, completed: list<int>, source: string}
     */
    public function open(User $user): array
    {
        $pools = $this->pools();
        if (! $pools) {
            throw new StickerException('sticker_book_empty');
        }

        $result = $this->db->transaction(function () use ($user, $pools): array {
            $collector = $this->collection->lock([(int) $user->id])[$user->id];
            $today = self::today();

            $claimed = $this->collection->collectors()->where('user_id', $user->id)
                ->where(fn (Builder $q) => $q->whereNull('daily_on')->orWhere('daily_on', '<', $today))
                ->update(['daily_on' => $today]);

            if ($claimed === 1) {
                $packId = (int) $this->packs()->insertGetId(['user_id' => $user->id, 'source' => 'daily', 'created_at' => Carbon::now()]);
            } else {
                $packId = (int) $this->packs()->where('user_id', $user->id)->whereNull('opened_at')->orderBy('id')->value('id');
            }

            if (! $packId || $this->packs()->where('id', $packId)->whereNull('opened_at')->update(['opened_at' => Carbon::now()]) !== 1) {
                throw new StickerException('sticker_no_pack');
            }

            $pityDue = $this->settings->pity() > 0 && (int) $collector->pity + 1 >= $this->settings->pity();
            $ids = $this->roll($pools, $pityDue);
            $source = (string) $this->packs()->where('id', $packId)->value('source');
            $this->packs()->where('id', $packId)->update(['contents' => implode(',', $ids)]);

            $counts = array_count_values($ids);
            $new = $this->collection->add((int) $user->id, $counts);

            $stickers = $this->catalog->all()['stickers'];
            $gotLegendary = (bool) array_filter($ids, fn ($id) => $stickers[$id]['rarity'] === Settings::LEGENDARY);
            $this->collection->collectors()->where('user_id', $user->id)->update([
                'pity' => $gotLegendary ? 0 : (int) $collector->pity + 1,
                'packs_opened' => (int) $collector->packs_opened + 1,
                'last_opened_at' => Carbon::now(),
            ]);

            $completed = $this->collection->settle((int) $user->id, array_keys($counts));
            $owned = $this->collection->owned()->where('user_id', $user->id)->whereIn('sticker_id', array_keys($counts))->pluck('count', 'sticker_id');

            // A sticker that is new shows "New" on its first copy only.
            $shownNew = [];
            $cards = [];
            foreach ($ids as $id) {
                $isNew = in_array($id, $new, true) && ! isset($shownNew[$id]);
                $shownNew[$id] = true;
                $cards[] = [...($this->catalog->card($id) ?? []), 'new' => $isNew, 'count' => (int) ($owned[$id] ?? 1)];
            }

            // Rarest last: the best card is the one turned over at the end.
            usort($cards, fn ($a, $b) => $a['rarity'] <=> $b['rarity']);

            // Two copies in one pack read ×3 then ×4, not ×4 twice: each card shows the count as it lands.
            $later = $counts;
            foreach ($cards as &$card) {
                $later[$card['id']]--;
                $card['count'] -= $later[$card['id']];
            }
            unset($card);

            return ['cards' => $cards, 'completed' => $completed, 'source' => $source];
        });

        $this->collection->awardBadges($user, $result['completed']);

        return $result;
    }

    /**
     * The stickers of a pack. The last slot is where the guarantees go: a
     * legendary when the pity timer is due, otherwise at least a rare when
     * the admin wants one in every pack.
     *
     * @param array<int, list<int>> $pools rarity => sticker ids
     * @return list<int>
     */
    public function roll(array $pools, bool $pityDue): array
    {
        $size = $this->settings->packSize();
        $weights = $this->settings->weights();
        $ids = [];
        $best = 0;

        for ($slot = 0; $slot < $size; $slot++) {
            $rarity = $this->pick($weights);
            if ($slot === $size - 1) {
                if ($pityDue && ! empty($pools[Settings::LEGENDARY]) && $best < Settings::LEGENDARY) {
                    $rarity = Settings::LEGENDARY;
                } elseif ($this->settings->guaranteeRare() && $best === 0 && $rarity === 0) {
                    $rarity = 1 + $this->pick(array_slice($weights, 1));
                }
            }
            $id = $this->draw($pools, $rarity);
            $best = max($best, $this->catalog->all()['stickers'][$id]['rarity']);
            $ids[] = $id;
        }

        return $ids;
    }

    /** @param list<int> $weights */
    private function pick(array $weights): int
    {
        $total = array_sum($weights);
        if ($total < 1) {
            return 0;
        }

        $roll = random_int(1, $total);
        foreach ($weights as $i => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * A sticker of this rarity, or the nearest rarity the forum has (lower
     * first: a set with no legendaries hands out an epic instead).
     *
     * @param array<int, list<int>> $pools
     */
    private function draw(array $pools, int $rarity): int
    {
        foreach ([...range($rarity, 0), ...range($rarity, Settings::LEGENDARY)] as $r) {
            if (! empty($pools[$r])) {
                return $pools[$r][random_int(0, count($pools[$r]) - 1)];
            }
        }

        throw new StickerException('sticker_book_empty');
    }

    /** @return array<int, list<int>> rarity => ids of stickers in live sets */
    private function pools(): array
    {
        $pools = [];
        foreach ($this->catalog->liveStickers() as $id => $sticker) {
            $pools[$sticker['rarity']][] = $id;
        }

        return $pools;
    }
}
