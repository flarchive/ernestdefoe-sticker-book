<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Anyone may look at albums; members collect, trade and react.
return Migration::addPermissions([
    'sticker-book.view' => Group::GUEST_ID,
    'sticker-book.collect' => Group::MEMBER_ID,
    'sticker-book.trade' => Group::MEMBER_ID,
    'sticker-book.react' => Group::MEMBER_ID,
]);
