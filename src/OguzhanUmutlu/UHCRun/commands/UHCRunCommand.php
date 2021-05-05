<?php

namespace OguzhanUmutlu\UHCRun\commands;

use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\arena\ArenaStatus;
use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;

class UHCRunCommand {
    public function onCommand(?CommandSender $player, array $args): void {
        if($player instanceof ConsoleCommandSender) {
            $player->sendMessage("§c> Use this command in-game.");
            return;
        }
        if(!isset($args[0])) {
            if($player) $player->sendMessage(
                "§e> UHCRun commands:\n".
                "§a> /uhcrun help - Shows commands\n".
                "§a> /uhcrun setmainlobby - Sets lobby that when players leave from arena\n".
                "§a> /uhcrun setwaitlobby - Sets waiting lobby\n".
                "§a> /uhcrun arenas - Shows all arenas\n".
                "§a> /uhcrun joinarena <arena"."> (player) - Join to arena\n".
                "§a> /uhcrun leavearena (player) - Leaves from arena\n".
                "§a> /uhcrun forcestart (arena) - Starts the arena\n".
                "§a> /uhcrun remove (arena) - Stops and removes the arena"
            );
            return;
        }
        if($args[0] == "setmainlobby") {
            if(!$player instanceof Player) return;
            UHCRun::getInstance()->getConfig()->setNested("mainlobby", $player->getLevel()->getFolderName().":".$player->getFloorX().":".$player->getFloorY().":".$player->getFloorZ());
            UHCRun::getInstance()->getConfig()->save();
            UHCRun::getInstance()->getConfig()->reload();
            $player->sendMessage("§a> Main lobby has been set to your location.");
        } else if($args[0] == "setwaitlobby") {
            if(!$player instanceof Player) return;
            UHCRun::getInstance()->getConfig()->setNested("waitlobby", $player->getLevel()->getFolderName().":".$player->getFloorX().":".$player->getFloorY().":".$player->getFloorZ());
            UHCRun::getInstance()->getConfig()->save();
            UHCRun::getInstance()->getConfig()->reload();
            $player->sendMessage("§a> Wait lobby has been set to your location.");
        } else if($args[0] == "arenas") {
            if(!$player instanceof CommandSender) return;
            $player->sendMessage("§e> Active arenas' ids: §b".implode("§e, §b", array_map(function($n){return $n->id;},UHCRun::getInstance()->manager->getArenas())));
        } else if($args[0] == "joinarena") {
            if(!isset($args[1]) || !is_numeric($args[1]) || !UHCRun::getInstance()->manager->getArenaFromId((int)$args[1])) {
                $player->sendMessage("§c> You should enter a valid arena ID.");
                return;
            }
            $select = $player;
            if(!isset($args[2]) && !$player instanceof Player) {
                $player->sendMessage("§c> Usage: /uhcrun joinarena <arena"."> <player".">");
                return;
            }
            if(isset($args[2])) {
                $select = UHCRun::getInstance()->getServer()->getPlayerExact($args[2]);
                if(!$select) {
                    $player->sendMessage("§c> Player not found.");
                    return;
                }
            }
            if(!is_numeric($args[1])) {
                $player->sendMessage("§c> You should enter a numeric value for arena.");
                return;
            }
            if(UHCRun::getInstance()->manager->getPlayerArena($select)) {
                $player->sendMessage("§c> This player is already in an arena.");
                return;
            }
            $arena = UHCRun::getInstance()->manager->getArenaFromId((int)$args[1]);
            if(count($arena->getPlayerManager()->getAlivePlayers()) > 15) {
                $player->sendMessage("§c> Arena is full.");
                return;
            }
            $arena->getPlayerManager()->addAlivePlayer($select);
            $player->sendMessage("§a> Player successfully added to arena.");
        } else if($args[0] == "leavearena") {
            if(!isset($args[1]) && !$player instanceof Player) {
                $player->sendMessage("§c> Usage: /uhcrun leavearena <player".">");
                return;
            }
            $select = $player;
            if(isset($args[1])) {
                $select = UHCRun::getInstance()->getServer()->getPlayerExact($args[1]);
                if(!$select) {
                    $player->sendMessage("§c> Player not found.");
                    return;
                }
            }
            $arena = UHCRun::getInstance()->manager->getPlayerArena($select);
            $arena->getPlayerManager()->removeAlivePlayer($select);
            $player->sendMessage("§a> Player successfully left from arena.");
        } else if($args[0] == "forcestart") {
            if(!isset($args[1]) && (!$player instanceof Player || !UHCRun::getInstance()->manager->getPlayerArena($player))) {
                $player->sendMessage("§c> Usage: /uhcrun forcestart <arena".">");
                return;
            }
            $arena = UHCRun::getInstance()->manager->getPlayerArena($player);
            if(isset($args[1])) {
                if(!is_numeric($args[1])) {
                    $player->sendMessage("§c> You should enter a numeric value for arena.");
                    return;
                }
                $arena = UHCRun::getInstance()->manager->getArenaFromId((int)$args[1]);
            }
            if($arena->status > ArenaStatus::STATUS_STARTING) {
                $player->sendMessage("§c> Arena is already started.");
                return;
            }
            $arena->task->countdown = 0;
            $arena->status = ArenaStatus::STATUS_STARTED;
            $arena->setFlag("break", true);
            $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["event-invincibility"]);
            $this->countdown = 0;
            foreach($arena->getPlayerManager()->getAlivePlayers() as $pl) {
                $x = random_int(0-$arena->border, $arena->border);
                $z = random_int(0-$arena->border, $arena->border);
                $pl->teleport(new Position($x, 100, $z, $arena->level));
            }
            $player->sendMessage("§a> Arena started.");
        } else if($args[0] == "remove") {
            if((!isset($args[1]) && !$player instanceof Player) || !UHCRun::getInstance()->manager->getPlayerArena($player)) {
                $player->sendMessage("§c> Usage: /uhcrun remove <arena".">");
                return;
            }
            $arena = UHCRun::getInstance()->manager->getPlayerArena($player);
            if(isset($args[1])) {
                if(!is_numeric($args[1])) {
                    $player->sendMessage("§c> You should enter a numeric value for arena.");
                    return;
                }
                $arena = UHCRun::getInstance()->manager->getArenaFromId((int)$args[1]);
            }
            UHCRun::getInstance()->manager->removeArena($arena);
            $player->sendMessage("§a> Arena removed.");
        } else $this->onCommand($player, []);
    }
}