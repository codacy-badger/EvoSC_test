<?php

namespace esc\Classes;


use esc\Controllers\ChatController;
use esc\Models\Player;

class ChatCommand
{
    public $trigger;
    public $command;
    public $callback;
    public $description;
    public $access;

    public function __construct(string $trigger, string $command, array $callback, string $description = '', string $access = null)
    {
        $this->trigger     = $trigger;
        $this->command     = $command;
        $this->callback    = $callback;
        $this->description = $description;
        $this->access      = $access;
    }

    public function hasAccess(Player $player)
    {
        if ($this->access == null) {
            return true;
        }

        return $player->hasAccess($this->access);
    }

    public static function add(string $command, array $callback, string $description = '-', string $trigger = '/', string $access = null)
    {
        ChatController::addCommand($command, $callback, $description, $trigger, $access);
    }

    public function compile()
    {
        return $this->trigger . $this->command;
    }

    public function run(array $arguments)
    {
        Log::logAddLine('ChatCommand', sprintf('Call: %s -> %s(%s)', $this->callback[0], $this->callback[1], implode(', ', $arguments)), false);
        call_user_func_array($this->callback, $arguments);
    }
}