<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * A pack a member has earned. Unopened while opened_at is null; opening sets
 * it in one guarded UPDATE (so a pack opens once) and records what was in it.
 * `source` is daily, post, likes or gift.
 */
return Migration::createTable('sticker_packs', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->string('source', 16);
    $table->string('contents', 255)->nullable();
    $table->dateTime('created_at');
    $table->dateTime('opened_at')->nullable();

    $table->index(['user_id', 'opened_at']);
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
