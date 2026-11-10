<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Throwable;

/**
 * What members own. Writes happen inside the caller's transaction, after the
 * member's collector row is locked (Collection::lock), so one member's packs
 * and trades run one after another.
 */
class Collection
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Catalog $catalog,
        private readonly ExtensionManager $extensions,
    ) {
    }

    public function owned(): Builder
    {
        return $this->db->table('sticker_owned');
    }

    public function collectors(): Builder
    {
        return $this->db->table('sticker_collectors');
    }

    /**
     * The collector rows of these members, created when missing and locked
     * for the rest of the transaction, in id order (two trades between the
     * same members can't deadlock).
     *
     * @param list<int> $userIds
     * @return array<int, object>
     */
    public function lock(array $userIds): array
    {
        sort($userIds);
        $this->collectors()->insertOrIgnore(array_map(fn ($id) => ['user_id' => $id], $userIds));

        return $this->collectors()->whereIn('user_id', $userIds)->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id')->all();
    }

    /**
     * Add stickers to a member. A row is made with count 0 and then raised,
     * so two additions at once both count.
     *
     * @param array<int, int> $counts sticker id => how many
     * @return list<int> The sticker ids they didn't have before.
     */
    public function add(int $userId, array $counts): array
    {
        if (! $counts) {
            return [];
        }

        $ids = array_keys($counts);
        $had = $this->owned()->where('user_id', $userId)->whereIn('sticker_id', $ids)->where('count', '>', 0)->pluck('sticker_id')->map(fn ($id) => (int) $id)->all();

        $now = Carbon::now();
        $this->owned()->insertOrIgnore(array_map(fn ($id) => ['user_id' => $userId, 'sticker_id' => $id, 'count' => 0, 'first_at' => $now], $ids));

        $byAmount = [];
        foreach ($counts as $id => $n) {
            $byAmount[$n][] = $id;
        }
        foreach ($byAmount as $n => $group) {
            $this->owned()->where('user_id', $userId)->whereIn('sticker_id', $group)->increment('count', $n);
        }

        return array_values(array_diff($ids, $had));
    }

    /**
     * Take spares: only ever leaves at least one, in a guarded UPDATE, so
     * nothing can go below what the member holds.
     */
    public function takeSpares(int $userId, int $stickerId, int $n): bool
    {
        return $this->owned()->where('user_id', $userId)->where('sticker_id', $stickerId)->where('count', '>', $n)->decrement('count', $n) === 1;
    }

    /** @return array<int, int> sticker id => count, for one member. */
    public function countsFor(int $userId): array
    {
        return $this->owned()->where('user_id', $userId)->where('count', '>', 0)->pluck('count', 'sticker_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * After stickers arrive: the member's distinct count, and the sets the
     * arrivals completed (recorded once each).
     *
     * @param list<int> $stickerIds what just arrived
     * @return list<int> Sets completed just now.
     */
    public function settle(int $userId, array $stickerIds): array
    {
        $catalog = $this->catalog->all();
        $unique = $this->owned()->where('user_id', $userId)->where('count', '>', 0)->count();
        $this->collectors()->where('user_id', $userId)->update(['unique_count' => $unique]);

        $touched = [];
        foreach ($stickerIds as $id) {
            $setId = $catalog['stickers'][$id]['setId'] ?? null;
            if ($setId) {
                $touched[$setId] = true;
            }
        }

        $completed = [];
        foreach ($this->catalog->liveSets() as $set) {
            if (! isset($touched[$set['id']])) {
                continue;
            }
            $have = $this->owned()->where('user_id', $userId)->whereIn('sticker_id', $set['stickers'])->where('count', '>', 0)->count();
            if ($have === count($set['stickers'])
                && $this->db->table('sticker_completions')->insertOrIgnore(['user_id' => $userId, 'set_id' => $set['id'], 'completed_at' => Carbon::now()]) === 1) {
                $completed[] = (int) $set['id'];
            }
        }

        return $completed;
    }

    /**
     * The set's FoF Badges badge, when the admin chose one and FoF Badges is
     * on. Called after the transaction: a badge problem never undoes a pack.
     *
     * @param list<int> $setIds
     */
    public function awardBadges(User $user, array $setIds): void
    {
        if (! $setIds || ! $this->extensions->isEnabled('fof-badges') || ! class_exists(\FoF\Badges\BadgeAwarder::class)) {
            return;
        }

        $sets = array_filter($this->catalog->all()['sets'], fn ($set) => in_array($set['id'], $setIds, true) && $set['badgeId']);
        foreach ($sets as $set) {
            try {
                $badge = \FoF\Badges\Badge::query()->find($set['badgeId']);
                if ($badge) {
                    resolve(\FoF\Badges\BadgeAwarder::class)->award($user, $badge, \FoF\Badges\UserBadge::GRANTED_BY_SYSTEM);
                }
            } catch (Throwable $e) {
                resolve('log')->warning('[sticker-book] Could not award the badge for set '.$set['id'].': '.$e->getMessage());
            }
        }
    }
}
