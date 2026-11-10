<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// A set a member completed, kept even if the admin adds stickers to it later.
return Migration::createTable('sticker_completions', function (Blueprint $table) {
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('set_id');
    $table->dateTime('completed_at');

    $table->primary(['user_id', 'set_id']);
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('set_id')->references('id')->on('sticker_sets')->cascadeOnDelete();
});
