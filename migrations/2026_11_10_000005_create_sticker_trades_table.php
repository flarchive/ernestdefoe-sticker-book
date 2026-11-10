<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * A trade offer: `give` (spares from the sender) for `take` (spares from the
 * recipient), each a JSON list of [sticker id, count]. Nothing is held while
 * it is pending; accepting re-checks both sides inside one transaction.
 * status: pending, accepted, declined, cancelled, failed (a side no longer
 * had the spares) or expired.
 */
return Migration::createTable('sticker_trades', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('from_user_id');
    $table->unsignedInteger('to_user_id');
    $table->string('status', 12)->default('pending');
    $table->string('give', 500);
    $table->string('take', 500);
    $table->string('message', 200)->nullable();
    $table->dateTime('created_at');
    $table->dateTime('decided_at')->nullable();

    $table->index(['to_user_id', 'status']);
    $table->index(['from_user_id', 'status']);
    $table->foreign('from_user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('to_user_id')->references('id')->on('users')->cascadeOnDelete();
});
