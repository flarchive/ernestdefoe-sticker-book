<?php

namespace ErnestDefoe\StickerBook\Exception;

use RuntimeException;

/** A refusal with a code the browser turns into a sentence (Api\Errors). */
class StickerException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 422)
    {
        parent::__construct($errorCode);
    }
}
