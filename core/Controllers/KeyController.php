<?php

namespace esc\Controllers;


use esc\Classes\Config;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;

class KeyController implements ControllerInterface
{
    private static $binds;

    public static function init()
    {
        self::$binds = collect();

        Hook::add('PlayerConnect', [KeyController::class, 'playerConnect']);

        ManiaLinkEvent::add('keybind', [KeyController::class, 'executeBinds']);

        // self::createBind('Q', [self::class, 'reloadConfig']);
    }

    public static function reloadConfig(Player $player)
    {
        ChatController::message(onlinePlayers(), '_info', $player->group, ' ', $player, ' reloads config.');
        Config::loadConfigFiles();
        Hook::fire('ConfigUpdated');
    }

    /**
     * @param string      $key
     * @param array       $callback
     * @param string|null $access
     */
    public static function createBind(string $key, array $callback, string $access = null)
    {
        $bind = collect([]);

        $bind->key = $key;
        $bind->function = $callback;
        $bind->access = $access;

        self::$binds->push($bind);
    }

    public static function executeBinds(Player $player, string $key)
    {
        $binds = self::$binds->where('key', $key);

        if ($binds->count() == 0) {
            return;
        }

        foreach ($binds as $bind) {
            if ($bind->access != null && !$player->hasAccess($bind->access)) {
                //No access
                return;
            }

            Log::logAddLine('KeyBind', sprintf('Call: %s -> %s(%s)', $bind->function[0], $bind->function[1], $player),
                false);
            call_user_func($bind->function, $player);
        }
    }

    public static function sendKeybindsScript(Player $player)
    {
        $keys = self::$binds->pluck('key');

        if (count($keys) == 0) {
            return;
        }

        Template::show($player, 'keybinds', compact('keys'));
    }

    public static function playerConnect(Player $player)
    {
        self::sendKeybindsScript($player);
    }

    public static function getKeybinds(): Collection
    {
        return self::$binds;
    }
}