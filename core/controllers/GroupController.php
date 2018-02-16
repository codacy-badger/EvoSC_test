<?php

namespace esc\controllers;


use esc\classes\Database;
use Illuminate\Database\Schema\Blueprint;

class GroupController
{
    public static function init()
    {
        self::createTables();
    }

    private static function createTables()
    {
        $seed = [
            ['id' => 1, 'Name' => 'SuperAdmin', 'Protected' => true],
            ['id' => 2, 'Name' => 'Admin', 'Protected' => true],
            ['id' => 3, 'Name' => 'Moderator', 'Protected' => true],
            ['id' => 4, 'Name' => 'Player', 'Protected' => true]
        ];

        Database::create('groups', function(Blueprint $table){
            $table->increments('id');
            $table->string('Name')->unique();
            $table->boolean('Protected')->default(false);
        }, $seed);
    }
}