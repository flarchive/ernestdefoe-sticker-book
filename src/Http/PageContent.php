<?php

namespace ErnestDefoe\StickerBook\Http;

use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Psr\Http\Message\ServerRequestInterface;

/** /stickers as the server sends it: a title, a canonical URL, not indexed (it is a member's own page). */
class PageContent
{
    public function __construct(private readonly UrlGenerator $url, private readonly TranslatorInterface $translator)
    {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): Document
    {
        $document->title = (string) $this->translator->trans('ernestdefoe-sticker-book.forum.page.title');
        $document->canonicalUrl = $this->url->to('forum')->route('stickers');
        $document->meta['robots'] = 'noindex';

        return $document;
    }
}
