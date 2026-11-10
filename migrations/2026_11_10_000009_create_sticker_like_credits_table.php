<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * A like that counted toward an extra pack: each member's like on a post
 * counts once, ever. Kept when the like is taken back, so unliking and
 * liking again earns nothing.
 */
return Migration::createTable('sticker_like_credits', function (Blueprint $table) {
    $table->unsignedInteger('post_id');
    $table->unsignedInteger('user_id');

    $table->primary(['post_id', 'user_id']);
    $table->index('user_id');
    $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
