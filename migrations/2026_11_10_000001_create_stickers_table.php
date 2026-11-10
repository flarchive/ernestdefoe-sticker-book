<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * A sticker in a set. `rarity` is 0 common, 1 rare, 2 epic, 3 legendary.
 * `image` holds three stored files with unrelated random names (the art,
 * a small copy and the silhouette shown to members who don't have it yet),
 * so the art's address can't be guessed from the silhouette's.
 */
return Migration::createTable('stickers', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('set_id');
    $table->string('name', 100);
    $table->unsignedTinyInteger('rarity')->default(0);
    $table->text('image')->nullable();
    $table->unsignedSmallInteger('position')->default(0);
    $table->timestamps();

    $table->index(['set_id', 'position']);
    $table->foreign('set_id')->references('id')->on('sticker_sets')->cascadeOnDelete();
});
