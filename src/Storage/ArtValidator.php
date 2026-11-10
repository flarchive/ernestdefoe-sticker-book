<?php

namespace ErnestDefoe\StickerBook\Storage;

use Flarum\Foundation\AbstractImageValidator;

/** Sticker art: PNG, WebP or JPEG up to 4 MB (no GIF: an animation would be flattened to one frame). Transparent PNG or WebP looks best. */
class ArtValidator extends AbstractImageValidator
{
    public function getMaxSize(): int
    {
        return 4096;
    }

    /** @return list<string> */
    protected function getAllowedTypes(): array
    {
        return ['png', 'webp', 'jpeg', 'jpg'];
    }
}
