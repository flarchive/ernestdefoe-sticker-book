<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * A member's collecting state, one row each, created on first use.
 *  - daily_on: the day (UTC) their free pack was last claimed.
 *  - post_on: the day their "first post of the day" pack was last given.
 *  - extra_on / extra_count: extra packs earned that day, for the daily cap.
 *  - like_progress: likes received toward the next pack (an unlike takes one back).
 *  - pity: packs opened since their last legendary.
 *  - unique_count: distinct stickers owned, for the collectors board.
 */
return Migration::createTable('sticker_collectors', function (Blueprint $table) {
    $table->unsignedInteger('user_id')->primary();
    $table->date('daily_on')->nullable();
    $table->date('post_on')->nullable();
    $table->date('extra_on')->nullable();
    $table->unsignedSmallInteger('extra_count')->default(0);
    $table->unsignedSmallInteger('like_progress')->default(0);
    $table->unsignedInteger('pity')->default(0);
    $table->unsignedInteger('packs_opened')->default(0);
    $table->unsignedInteger('unique_count')->default(0);
    $table->dateTime('last_opened_at')->nullable();

    $table->index('unique_count');
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
