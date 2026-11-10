<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Api\Context;
use Illuminate\Database\ConnectionInterface;
use Psr\Http\Message\ServerRequestInterface;
use WeakMap;

/**
 * Sticker reactions under posts. 🚨 One query per request however many posts
 * are on the page: each post's getter queues its id and returns a deferred
 * value; the first one resolved loads every queued post's reactions, grouped
 * by sticker, with whether the viewer chose each.
 */
class Reactions
{
    /** Different stickers shown under one post. */
    public const SHOWN = 12;

    /** @var WeakMap<ServerRequestInterface, array{queued: array<int, true>, rows: array<int, list<array<string, mixed>>>}> */
    private static WeakMap $byRequest;

    public function __construct(private readonly ConnectionInterface $db, private readonly Catalog $catalog)
    {
        self::$byRequest ??= new WeakMap();
    }

    public function deferred(Context $context, int $postId): \Closure
    {
        $state = self::$byRequest[$context->request] ?? ['queued' => [], 'rows' => []];
        if (! array_key_exists($postId, $state['rows'])) {
            $state['queued'][$postId] = true;
        }
        self::$byRequest[$context->request] = $state;

        return function () use ($context, $postId) {
            $state = self::$byRequest[$context->request];
            if ($state['queued']) {
                $ids = array_keys($state['queued']);
                $loaded = $this->load($ids, (int) $context->getActor()->id);
                foreach ($ids as $id) {
                    $state['rows'][$id] = $loaded[$id] ?? [];
                }
                $state['queued'] = [];
                self::$byRequest[$context->request] = $state;
            }

            return $state['rows'][$postId] ?: null;
        };
    }

    /**
     * @param list<int> $postIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function load(array $postIds, int $viewerId): array
    {
        $grammar = $this->db->getQueryGrammar();
        $mine = $viewerId
            ? 'MAX(CASE WHEN '.$grammar->wrap('user_id').' = '.$viewerId.' THEN 1 ELSE 0 END)'
            : '0';

        $rows = $this->db->table('sticker_reactions')
            ->whereIn('post_id', $postIds)
            ->groupBy('post_id', 'sticker_id')
            ->selectRaw($grammar->wrap('post_id').' as post_id, '.$grammar->wrap('sticker_id').' as sticker_id, COUNT(*) as n, '.$mine.' as mine')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $card = $this->catalog->card((int) $row->sticker_id);
            if (! $card) {
                continue;
            }
            $out[(int) $row->post_id][] = ['id' => $card['id'], 'name' => $card['name'], 'rarity' => $card['rarity'], 'small' => $card['small'], 'count' => (int) $row->n, 'mine' => (bool) $row->mine];
        }

        foreach ($out as &$list) {
            usort($list, fn ($a, $b) => [$b['count'], $b['rarity']] <=> [$a['count'], $a['rarity']]);
            $list = array_slice($list, 0, self::SHOWN);
        }

        return $out;
    }
}
