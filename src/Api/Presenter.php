<?php

namespace ErnestDefoe\StickerBook\Api;

use Flarum\Http\SlugManager;
use Flarum\User\User;

/** Members as the browser's store wants them: one compact row each. */
final class Presenter
{
    /**
     * @param iterable<User> $users
     * @return list<array<string, mixed>>
     */
    public static function users(iterable $users): array
    {
        $slugger = resolve(SlugManager::class)->forResource(User::class);
        $out = [];
        foreach ($users as $user) {
            $out[] = [
                'id' => (int) $user->id,
                'username' => $user->username,
                'displayName' => $user->display_name,
                'slug' => $slugger->toSlug($user),
                'avatarUrl' => $user->avatar_url,
            ];
        }

        return $out;
    }

    /**
     * Members the viewer may see, by id, in one query.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public static function visible(User $actor, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids ? self::users(User::query()->whereVisibleTo($actor)->whereIn('id', $ids)->get()) : [];
    }
}
