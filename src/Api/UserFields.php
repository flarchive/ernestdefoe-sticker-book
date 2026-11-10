<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Api\Context;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema\Attribute;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * `stickerShowcase` on a member's own profile (the user show endpoint only,
 * never lists): their rarest stickers, how many they have and the sets they
 * completed. Two small queries, on that one request.
 */
class UserFields
{
    public function __construct(private readonly Catalog $catalog, private readonly ConnectionInterface $db)
    {
    }

    /** @return list<Attribute> */
    public function __invoke(): array
    {
        return [
            Attribute::make('stickerShowcase')
                ->visible(fn (User $user, Context $context) => $context->showing(UserResource::class) && $context->getActor()->hasPermission(Settings::VIEW))
                ->get(fn (User $user) => $this->showcase((int) $user->id)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function showcase(int $userId): ?array
    {
        $live = $this->catalog->liveStickers();
        if (! $live) {
            return null;
        }

        $counts = $this->db->table('sticker_owned')->where('user_id', $userId)->where('count', '>', 0)->pluck('first_at', 'sticker_id');
        $owned = array_values(array_filter(array_map(fn ($id) => $live[(int) $id] ?? null, $counts->keys()->all())));
        if (! $owned) {
            return null;
        }

        // Rarest first; among equals, the one they got first.
        usort($owned, fn ($a, $b) => [$b['rarity'], (string) $counts[$a['id']]] <=> [$a['rarity'], (string) $counts[$b['id']]]);
        $completed = $this->db->table('sticker_completions')->where('user_id', $userId)->pluck('set_id')->map(fn ($id) => (int) $id)->all();
        $sets = array_values(array_filter($this->catalog->liveSets(), fn ($set) => in_array($set['id'], $completed, true)));

        return [
            'unique' => count($owned),
            'total' => count($live),
            'top' => array_map(fn ($s) => ['id' => $s['id'], 'name' => $s['name'], 'rarity' => $s['rarity'], 'small' => $s['small']], array_slice($owned, 0, 6)),
            'sets' => array_map(fn ($set) => $set['name'], $sets),
        ];
    }
}
