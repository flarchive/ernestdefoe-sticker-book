<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * A set of stickers ("Mascots", "Inside jokes"). A hidden set is the admin's
 * draft: it is in no pack and no album until shown. `badge_id` is the FoF
 * Badges badge a member earns for completing it (no foreign key: FoF Badges
 * may not be installed).
 */
return Migration::createTable('sticker_sets', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 100);
    $table->string('description', 255)->nullable();
    $table->unsignedInteger('badge_id')->nullable();
    $table->unsignedSmallInteger('position')->default(0);
    $table->boolean('is_hidden')->default(false);
    $table->timestamps();
});
