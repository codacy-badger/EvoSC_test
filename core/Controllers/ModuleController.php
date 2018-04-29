<?php

namespace esc\Controllers;

use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\Player;
use Illuminate\Support\Collection;

class ModuleController
{
    private static $loadedModules;

    public static function init()
    {
        self::$loadedModules = new Collection();

        Template::add('modules', File::get('core/Templates/modules.latte.xml'));

        ManiaLinkEvent::add('modules.close', 'esc\Controllers\ModuleController::hideModules');
        ManiaLinkEvent::add('module.reload', 'esc\Controllers\ModuleController::reloadModule');

        ChatCommand::add('modules', 'esc\Controllers\ModuleController::showModules', 'Display all loaded modules', '//', 'module.reload');
    }

    public static function reloadModule(Player $callee, string $moduleName)
    {
        $module = self::getModules()->where('name', $moduleName)->first();

        if ($module) {
            $module->load($callee);
            ChatController::messageAll('_info', $callee, ' reloads module ', $module);
        }
    }

    public static function getModules(): Collection
    {
        return self::$loadedModules;
    }

    public static function showModules(Player $player)
    {
        if (!$player->isMasteradmin()) {
            ChatController::message($player, warning('Access denied'));
            return;
        }

        $modules = Template::toString('modules', ['modules' => self::getModules()]);

        Template::show($player, 'esc.modal', [
            'id' => 'ModulesReloader',
            'title' => 'ModulesReloader $f00(by the love of god, do not touch!)',
            'width' => 180,
            'height' => 97,
            'content' => $modules
        ]);
    }

    public static function hideModules(Player $callee)
    {
        Template::hide($callee, 'modules');
    }

    public static function bootModules()
    {
        global $classes;

        $modules = $classes->filter(function ($class) {
            if (preg_match('/^esc\\Modules\\.+/', $class->namespace)) {
                return true;
            }

            return false;
        });

        Log::logAddLine('Modules', 'Booting modules');

        $modules->each(function($module){
            $class = new $module->class;
        });
    }
}