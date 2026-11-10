<?php

namespace ErnestDefoe\StickerBook\Notification;

use ErnestDefoe\StickerBook\Trade;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/**
 * A trade offer arrived (to the recipient), or the sender's offer was
 * accepted, declined or could no longer go through. The subject is the other
 * member (Users have an API resource); the trade rides in the data.
 */
class TradeBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(public Trade $trade, public User $by, public string $kind)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->by;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->by;
    }

    public function getData(): mixed
    {
        return ['trade' => $this->trade->id, 'kind' => $this->kind];
    }

    public static function getType(): string
    {
        return 'stickerTrade';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }
}
