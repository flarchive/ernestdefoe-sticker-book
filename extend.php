<?php

/*
 * Sticker Book: members collect stickers in an album.
 *
 * Admins make sets of stickers (art + rarity). Members get a free pack each
 * day and extra packs for taking part, open them with a reveal, fill their
 * album set by set (a completed set can award a FoF Badges badge), trade
 * spares with each other, show their rarest stickers on their profile and
 * put stickers on posts.
 *
 * 🚨 The server decides everything: what a pack holds (Packs, random_int),
 * whether a trade goes through (Trades, one transaction, guarded UPDATEs),
 * what an album shows (silhouettes for what a member doesn't have). The
 * browser only asks.
 *
 * forum.js wires up the routes, the pack button, the profile strip and the
 * post stickers; the pages, the reveal and the forms are lazy chunks.
 */

use ErnestDefoe\StickerBook\Admin;
use ErnestDefoe\StickerBook\Api;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Console\StarterCommand;
use ErnestDefoe\StickerBook\Gdpr\StickerData;
use ErnestDefoe\StickerBook\Http\PageContent;
use ErnestDefoe\StickerBook\Listener\EarnPacks;
use ErnestDefoe\StickerBook\Listener\ForgetDeletedUser;
use ErnestDefoe\StickerBook\Notification\TradeBlueprint;
use ErnestDefoe\StickerBook\Packs;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Foundation\Paths;
use Flarum\Gdpr\Extend\UserData;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Event\Posted;
use Flarum\User\Event\Deleted;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // 🚨 Not optional: the pages and the reveal are chunks in here, and undeclared chunks 404.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/stickers', 'stickers', PageContent::class)
        ->route('/u/{username}/stickers', 'user.stickers'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    // 🚨 String literals, not our own class constants: extend.php is read before our autoloader is guaranteed.
    (new Extend\Filesystem())
        ->disk('ernestdefoe-sticker-book', fn (Paths $paths, UrlGenerator $url) => [
            'root' => "$paths->public/assets/stickers",
            'url' => $url->to('forum')->path('assets/stickers'),
            'visibility' => 'public',
        ]),

    (new Extend\Routes('api'))
        ->get('/sticker-book/me', 'sticker-book.me', Api\StatusController::class)
        ->post('/sticker-book/open', 'sticker-book.open', Api\OpenController::class)
        ->get('/sticker-book/album', 'sticker-book.album', Api\AlbumController::class)
        ->get('/sticker-book/mine', 'sticker-book.mine', Api\MineController::class)
        ->get('/sticker-book/board', 'sticker-book.board', Api\BoardController::class)
        ->get('/sticker-book/spares', 'sticker-book.spares', Api\SparesController::class)
        ->get('/sticker-book/trades', 'sticker-book.trades', Api\TradesController::class)
        ->post('/sticker-book/trades', 'sticker-book.trades.offer', Api\OfferController::class)
        ->post('/sticker-book/trades/{id:\d+}/{action:accept|decline|cancel}', 'sticker-book.trades.act', Api\TradeActionController::class)
        ->post('/sticker-book/react', 'sticker-book.react', Api\ReactController::class)
        ->get('/sticker-book/admin', 'sticker-book.admin', Admin\CatalogController::class)
        ->post('/sticker-book/sets', 'sticker-book.sets.create', Admin\SetController::class)
        ->patch('/sticker-book/sets/{id:\d+}', 'sticker-book.sets.update', Admin\SetController::class)
        ->delete('/sticker-book/sets/{id:\d+}', 'sticker-book.sets.delete', Admin\SetController::class)
        ->post('/sticker-book/stickers', 'sticker-book.stickers.create', Admin\StickerController::class)
        ->post('/sticker-book/stickers/{id:\d+}', 'sticker-book.stickers.update', Admin\StickerController::class)
        ->delete('/sticker-book/stickers/{id:\d+}', 'sticker-book.stickers.delete', Admin\StickerController::class)
        ->post('/sticker-book/order', 'sticker-book.order', Admin\OrderController::class)
        ->post('/sticker-book/gift', 'sticker-book.gift', Admin\GiftController::class)
        ->post('/sticker-book/starter', 'sticker-book.starter', Admin\StarterController::class),

    (new Extend\Console())
        ->command(StarterCommand::class),

    (new Extend\Notification())
        ->type(TradeBlueprint::class, ['alert']),

    (new Extend\ApiResource(UserResource::class))
        ->fields(Api\UserFields::class),

    (new Extend\ApiResource(PostResource::class))
        ->fields(Api\PostFields::class),

    // The viewer's permissions are already loaded; the pack status is two small queries, on forum loads only, for members who collect.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('stickerCanView')
                ->get(fn ($forum, Context $context) => $context->getActor()->can(Settings::VIEW)),
            Schema\Boolean::make('stickerCanTrade')
                ->get(fn ($forum, Context $context) => $context->getActor()->can(Settings::TRADE)),
            Schema\Boolean::make('stickerCanReact')
                ->get(fn ($forum, Context $context) => $context->getActor()->can(Settings::REACT) && resolve(Settings::class)->reactions()),
            Schema\Attribute::make('stickerStatus')
                ->visible(fn ($forum, Context $context) => ! $context->getActor()->isGuest() && $context->getActor()->can(Settings::COLLECT) && resolve(Catalog::class)->liveStickers())
                ->get(fn ($forum, Context $context) => resolve(Packs::class)->status($context->getActor())),
        ]),

    (new Extend\Settings())
        ->default('ernestdefoe-sticker-book.nav', true)
        ->default('ernestdefoe-sticker-book.reactions', true)
        ->default('ernestdefoe-sticker-book.guarantee_rare', true)
        ->default('ernestdefoe-sticker-book.first_post', true)
        ->serializeToForum('stickerNav', 'ernestdefoe-sticker-book.nav', 'boolval'),

    (new Extend\Event())
        ->listen(Posted::class, [EarnPacks::class, 'posted'])
        ->listen(Deleted::class, ForgetDeletedUser::class),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-likes', fn () => [
            (new Extend\Event())
                ->listen(\Flarum\Likes\Event\PostWasLiked::class, fn ($event) => resolve(EarnPacks::class)->liked($event->post, $event->user)),
        ])
        ->whenExtensionEnabled('flarum-gdpr', fn () => [
            (new UserData())->addType(StickerData::class),
        ]),
];
