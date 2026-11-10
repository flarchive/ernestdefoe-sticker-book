<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Reactions;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Api\Context;
use Flarum\Api\Schema\Attribute;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;

/** `stickerReactions` on comment posts: batched, one query per page of posts (Reactions). */
class PostFields
{
    public function __construct(private readonly Reactions $reactions, private readonly Settings $settings)
    {
    }

    /** @return list<Attribute> */
    public function __invoke(): array
    {
        return [
            Attribute::make('stickerReactions')
                ->visible(fn (Post $post, Context $context) => $post instanceof CommentPost && $this->settings->reactions() && $context->getActor()->hasPermission(Settings::VIEW))
                ->get(fn (Post $post, Context $context) => $this->reactions->deferred($context, (int) $post->id)),
        ];
    }
}
