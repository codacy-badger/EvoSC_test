<?php

namespace esc\Modules\LocalRecords;

use esc\Classes\Config;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;

class LocalRecords
{
    /**
     * LocalRecords constructor.
     */
    public function __construct()
    {
        Hook::add('PlayerFinish', [LocalRecords::class, 'playerFinish']);
        Hook::add('BeginMap', [LocalRecords::class, 'beginMap']);
        Hook::add('PlayerConnect', [LocalRecords::class, 'showManialink']);
    }

    public static function reload(Player $player)
    {
        Config::configReload();
        TemplateController::loadTemplates();
        self::showManialink($player);
    }

    public static function showManialink(Player $player)
    {
        if ($player) {
            $map = MapController::getCurrentMap();

            if (!$map) {
                return;
            }

            $locals  = $map->locals()->orderBy('Rank')->get();
            $cpCount = (int)$map->gbx->CheckpointsPerLaps;

            $playerIds = $locals->pluck('Player');
            $players   = Player::whereIn('id', $playerIds)->get();

            $localsJson = $locals->map(function (LocalRecord $local) use ($players) {
                $player = $players->where('id', $local->Player)->first();

                return [
                    'rank'  => $local->Rank,
                    'cps'   => $local->Checkpoints,
                    'score' => $local->Score,
                    'nick'  => $player->NickName,
                    'login' => $player->Login,
                ];
            })->toJson();

            Template::show($player, 'local-records.manialink', compact('localsJson', 'cpCount'));
        }
    }

    /**
     * Called @ PlayerFinish
     *
     * @param Player $player
     * @param int    $score
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();

        $localCount = $map->locals()->count();

        $local = $map->locals()->wherePlayer($player->id)->first();
        if ($local != null) {
            if ($score == $local->Score) {
                ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' equaled his/her ', $local);

                return;
            }

            $oldRank = $local->Rank;

            if ($score < $local->Score) {
                $diff = $local->Score - $score;
                $local->update(['Score' => $score, 'Checkpoints' => $checkpoints]);
                $local = self::fixLocalRecordRanks($map, $player);

                if ($oldRank == $local->Rank) {
                    ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' secured his/her ', $local, ' (-' . formatScore($diff) . ')');
                } else {
                    ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' gained the ', $local, ' (-' . formatScore($diff) . ')');
                }
                Hook::fire('PlayerLocal', $player, $local);
                self::sendUpdatedLocals($map);
            }
        } else {
            if ($localCount < config('locals.limit')) {
                $map->locals()->create([
                    'Player'      => $player->id,
                    'Map'         => $map->id,
                    'Score'       => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank'        => 999,
                ]);
                $local = self::fixLocalRecordRanks($map, $player);
                ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' claimed the ', $local);
                Hook::fire('PlayerLocal', $player, $local);
                self::sendUpdatedLocals($map);
            }
        }
    }

    public static function sendUpdatedLocals(Map $map)
    {
        $locals    = $map->locals()->orderBy('Rank')->get();
        $playerIds = $locals->pluck('Player');
        $players   = Player::whereIn('id', $playerIds)->get();

        $localsJson = $locals->map(function (LocalRecord $local) use ($players) {
            $player = $players->where('id', $local->Player)->first();

            return [
                'rank'  => $local->Rank,
                'cps'   => $local->Checkpoints,
                'score' => $local->Score,
                'nick'  => $player->NickName,
                'login' => $player->Login,
            ];
        })->toJson();

        Template::showAll('local-records.update', compact('localsJson'));
    }

    /**
     * Fix local ranks
     *
     * @param Map         $map
     * @param Player|null $player
     *
     * @return null
     */
    private static function fixLocalRecordRanks(Map $map, Player $player = null)
    {
        $locals = $map->locals()->orderBy('Score')->get();
        $i      = 1;
        foreach ($locals as $local) {
            $local->update(['Rank' => $i]);
            $i++;
        }

        if ($player) {
            return $map->locals()->wherePlayer($player->id)->first();
        }

        return null;
    }

    /**
     * Called @ BeginMap
     */
    public static function beginMap()
    {
        onlinePlayers()->each([self::class, 'showManialink']);
    }
}