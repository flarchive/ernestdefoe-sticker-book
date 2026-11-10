<?php

namespace ErnestDefoe\StickerBook\Admin;

use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Catalog;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/sticker-book/admin: every set (hidden ones too) with its stickers, how many members have each, and FoF Badges' badges to pick from. */
class CatalogController extends Endpoint
{
    public function __construct(private readonly Catalog $catalog, private readonly ExtensionManager $extensions, private readonly ConnectionInterface $db)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        $all = $this->catalog->all();
        $owners = $this->db->table('sticker_owned')->groupBy('sticker_id')->selectRaw('sticker_id, COUNT(*) as n')->pluck('n', 'sticker_id');
        $badges = [];
        if ($this->extensions->isEnabled('fof-badges') && class_exists(\FoF\Badges\Badge::class)) {
            $badges = \FoF\Badges\Badge::query()->orderBy('name')->get(['id', 'name', 'icon'])
                ->map(fn ($badge) => ['id' => $badge->id, 'name' => $badge->name, 'icon' => $badge->icon])->all();
        }

        return [
            'sets' => array_map(fn ($set) => [
                ...$set,
                'stickers' => array_map(fn ($id) => [...$all['stickers'][$id], 'owners' => (int) ($owners[$id] ?? 0)], $set['stickers']),
            ], $all['sets']),
            'badges' => $badges,
        ];
    }
}
